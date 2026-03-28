<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\SpreadsheetReader;
use App\Core\Validator;
use App\Models\Employee;
use Throwable;

class EmployeeController extends Controller
{
    private Employee $employees;

    public function __construct()
    {
        $this->employees = new Employee();
    }

    public function index(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');
        $departmentId = $request->query('department_id') !== null ? (int) $request->query('department_id') : null;
        $scopeEmployeeIds = $request->attribute('scope_employee_ids');

        $result = $this->employees->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($status) ? $status : null,
            $departmentId,
            is_array($scopeEmployeeIds) ? $scopeEmployeeIds : null
        );

        return $this->ok(
            $result['items'],
            'Employee list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function show(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $employee = $this->employees->findWithDepartment($id);
        if ($employee === null) {
            throw new HttpException('Employee not found', 404, 'not_found');
        }
        return $this->ok($employee, 'Employee detail');
    }

    public function store(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'employee_code' => ['required', 'string'],
            'full_name' => ['required', 'string'],
            'company_email' => ['required', 'email'],
            'password' => ['string'],
            'hire_date' => ['required', 'date'],
            'date_of_birth' => ['date'],
            'gender' => ['string'],
            'phone_number' => ['string'],
            'status' => ['string'],
            'nationality_id' => ['integer'],
            'department_id' => ['integer'],
            'position_id' => ['integer'],
        ]);

        $departmentId = isset($payload['department_id']) ? (int) $payload['department_id'] : null;
        $positionId = isset($payload['position_id']) ? (int) $payload['position_id'] : null;

        $rawPassword = trim((string) ($payload['password'] ?? ''));
        unset($payload['password']);
        $payload['password_hash'] = password_hash(
            $rawPassword !== '' ? $rawPassword : (string) $payload['employee_code'],
            PASSWORD_BCRYPT
        );

        $authUser = $request->attribute('auth_user');
        $payload['created_by'] = (int) ($authUser['employee_id'] ?? 1);
        $payload['updated_by'] = (int) ($authUser['employee_id'] ?? 1);

        $id = $this->employees->create($payload);

        if ($departmentId !== null && $positionId !== null && $departmentId > 0 && $positionId > 0) {
            $this->employees->setCurrentEmployment(
                $id,
                $departmentId,
                $positionId,
                (string) $payload['hire_date'],
                (int) ($payload['created_by'] ?? 0),
                'Employee created from admin module'
            );
        }

        $created = $this->employees->findWithDepartment($id);
        return $this->created($created, 'Employee created');
    }

    public function update(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->employees->find($id);
        if ($existing === null) {
            throw new HttpException('Employee not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'full_name' => ['string'],
            'company_email' => ['email'],
            'password' => ['string'],
            'date_of_birth' => ['date'],
            'gender' => ['string'],
            'phone_number' => ['string'],
            'status' => ['string'],
            'hire_date' => ['date'],
            'nationality_id' => ['integer'],
            'department_id' => ['integer'],
            'position_id' => ['integer'],
        ]);

        if (array_key_exists('password', $payload)) {
            $newPassword = trim((string) $payload['password']);
            unset($payload['password']);
            if ($newPassword !== '') {
                $payload['password_hash'] = password_hash($newPassword, PASSWORD_BCRYPT);
            }
        }

        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 1);
        $payload['updated_by'] = $actorId;

        $hasDepartment = array_key_exists('department_id', $payload);
        $hasPosition = array_key_exists('position_id', $payload);
        $targetDepartmentId = $hasDepartment ? (int) $payload['department_id'] : null;
        $targetPositionId = $hasPosition ? (int) $payload['position_id'] : null;
        unset($payload['department_id'], $payload['position_id']);

        if ($payload !== []) {
            $this->employees->updateById($id, $payload);
        }

        if ($hasDepartment || $hasPosition) {
            $currentEmployment = $this->employees->findCurrentEmployment($id);
            if ($currentEmployment === null && (!$hasDepartment || !$hasPosition)) {
                throw new HttpException(
                    'department_id and position_id are required for employee without current employment',
                    422,
                    'validation_error'
                );
            }

            if (!$hasDepartment) {
                $targetDepartmentId = (int) ($currentEmployment['department_id'] ?? 0);
            }
            if (!$hasPosition) {
                $targetPositionId = (int) ($currentEmployment['position_id'] ?? 0);
            }

            if (($targetDepartmentId ?? 0) <= 0 || ($targetPositionId ?? 0) <= 0) {
                throw new HttpException('department_id and position_id are invalid', 422, 'validation_error');
            }

            $startDate = isset($payload['hire_date']) ? (string) $payload['hire_date'] : date('Y-m-d');
            $this->employees->setCurrentEmployment(
                $id,
                (int) $targetDepartmentId,
                (int) $targetPositionId,
                $startDate,
                $actorId,
                'Employee profile updated'
            );
        }

        return $this->ok($this->employees->findWithDepartment($id), 'Employee updated');
    }

    public function destroy(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->employees->find($id);
        if ($existing === null) {
            throw new HttpException('Employee not found', 404, 'not_found');
        }

        $this->employees->deleteById($id);
        return $this->ok(null, 'Employee deleted');
    }

    public function importProbation(Request $request): array
    {
        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            throw new HttpException('File is required (field name: file).', 422, 'validation_error');
        }

        $upload = $_FILES['file'];
        $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new HttpException('Uploaded file is invalid.', 422, 'validation_error');
        }

        $tmpName = (string) ($upload['tmp_name'] ?? '');
        $originalName = (string) ($upload['name'] ?? '');
        if ($tmpName === '' || $originalName === '') {
            throw new HttpException('Uploaded file is invalid.', 422, 'validation_error');
        }

        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 1);
        $dryRun = $this->toBoolean($request->input('dry_run'), false);
        $createContract = $this->toBoolean($request->input('create_contract'), true);
        $createHistory = $this->toBoolean($request->input('create_history'), true);

        $spreadsheet = SpreadsheetReader::read($tmpName, $originalName);
        $rows = $spreadsheet['rows'] ?? [];
        if (!is_array($rows) || $rows === []) {
            throw new HttpException('Spreadsheet has no data rows.', 422, 'validation_error');
        }

        $contractType = null;
        if ($createContract) {
            $contractType = $this->employees->probationContractType();
            if ($contractType === null) {
                throw new HttpException(
                    'No probation contract type found. Please seed contract_types first.',
                    422,
                    'validation_error'
                );
            }
        }

        $maxRows = 3000;
        if (count($rows) > $maxRows) {
            throw new HttpException('Maximum import rows exceeded (' . $maxRows . ').', 422, 'validation_error');
        }

        $seenCodes = [];
        $seenEmails = [];
        $prepared = [];
        $errors = [];

        foreach ($rows as $index => $rawRow) {
            $excelRow = $index + 2;
            if (!is_array($rawRow)) {
                $errors[] = ['row' => $excelRow, 'error' => 'Invalid row format'];
                continue;
            }

            $mapped = $this->mapImportRow($rawRow);
            if ($this->isImportRowEmpty($mapped)) {
                continue;
            }

            $employeeCode = trim((string) ($mapped['employee_code'] ?? ''));
            $fullName = trim((string) ($mapped['full_name'] ?? ''));
            $companyEmail = trim((string) ($mapped['company_email'] ?? ''));
            $hireDate = $this->normalizeDate($mapped['hire_date'] ?? null);

            if ($employeeCode === '' || $fullName === '' || $companyEmail === '' || $hireDate === null) {
                $errors[] = [
                    'row' => $excelRow,
                    'error' => 'Required fields: employee_code, full_name, company_email, hire_date',
                ];
                continue;
            }
            if (filter_var($companyEmail, FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = ['row' => $excelRow, 'error' => 'Invalid company_email'];
                continue;
            }

            $lowerCode = strtolower($employeeCode);
            $lowerEmail = strtolower($companyEmail);
            if (isset($seenCodes[$lowerCode])) {
                $errors[] = ['row' => $excelRow, 'error' => 'Duplicate employee_code in file: ' . $employeeCode];
                continue;
            }
            if (isset($seenEmails[$lowerEmail])) {
                $errors[] = ['row' => $excelRow, 'error' => 'Duplicate company_email in file: ' . $companyEmail];
                continue;
            }
            $seenCodes[$lowerCode] = true;
            $seenEmails[$lowerEmail] = true;

            if ($this->employees->existsByEmployeeCode($employeeCode)) {
                $errors[] = ['row' => $excelRow, 'error' => 'employee_code already exists: ' . $employeeCode];
                continue;
            }
            if ($this->employees->existsByCompanyEmail($companyEmail)) {
                $errors[] = ['row' => $excelRow, 'error' => 'company_email already exists: ' . $companyEmail];
                continue;
            }

            $prepared[] = [
                'row' => $excelRow,
                'employee_code' => $employeeCode,
                'full_name' => $fullName,
                'company_email' => $companyEmail,
                'phone_number' => $this->nullIfEmpty($mapped['phone_number'] ?? null),
                'hire_date' => $hireDate,
                'date_of_birth' => $this->normalizeDate($mapped['date_of_birth'] ?? null),
                'gender' => $this->normalizeGender($mapped['gender'] ?? null),
                'nationality_id' => $this->toNullableInt($mapped['nationality_id'] ?? 1) ?? 1,
                'department_id' => $this->toNullableInt($mapped['department_id'] ?? null),
                'position_id' => $this->toNullableInt($mapped['position_id'] ?? null),
                'basic_salary' => $this->toMoney($mapped['basic_salary'] ?? null),
                'gross_salary' => $this->toMoney($mapped['gross_salary'] ?? null),
                'net_salary' => $this->toMoney($mapped['net_salary'] ?? null),
                'probation_months' => $this->toNullableInt($mapped['probation_months'] ?? null),
                'contract_code' => $this->nullIfEmpty($mapped['contract_code'] ?? null),
                'contract_number' => $this->nullIfEmpty($mapped['contract_number'] ?? null),
                'job_title' => $this->nullIfEmpty($mapped['job_title'] ?? null),
                'work_location' => $this->nullIfEmpty($mapped['work_location'] ?? null),
            ];
        }

        if ($dryRun) {
            return $this->ok([
                'mode' => 'dry_run',
                'total_rows' => count($rows),
                'ready_rows' => count($prepared),
                'error_rows' => count($errors),
                'errors' => $errors,
            ], 'Probation employee import validated');
        }

        $db = Database::connection();
        $created = [];

        try {
            $db->beginTransaction();

            foreach ($prepared as $item) {
                $employeeId = $this->employees->create([
                    'employee_code' => $item['employee_code'],
                    'full_name' => $item['full_name'],
                    'date_of_birth' => $item['date_of_birth'],
                    'gender' => $item['gender'],
                    'phone_number' => $item['phone_number'],
                    'company_email' => $item['company_email'],
                    'password_hash' => password_hash($item['employee_code'], PASSWORD_BCRYPT),
                    'nationality_id' => $item['nationality_id'],
                    'status' => 'THỬ_VIỆC',
                    'hire_date' => $item['hire_date'],
                    'seniority_start_date' => $item['hire_date'],
                    'base_leave_days' => 12.00,
                    'created_by' => $actorId,
                    'updated_by' => $actorId,
                ]);

                if ($createHistory && $item['department_id'] !== null && $item['position_id'] !== null) {
                    $this->employees->createEmploymentHistory([
                        'employee_id' => $employeeId,
                        'department_id' => $item['department_id'],
                        'position_id' => $item['position_id'],
                        'start_date' => $item['hire_date'],
                        'end_date' => null,
                        'is_current' => 1,
                        'notes' => 'Imported from probation spreadsheet',
                        'created_by' => $actorId,
                    ]);
                }

                if ($createContract && is_array($contractType)) {
                    $months = $item['probation_months'] ?? null;
                    if ($months === null || $months <= 0) {
                        $months = (int) ($contractType['max_duration_months'] ?? 2);
                        if ($months <= 0) {
                            $months = 2;
                        }
                    }

                    $effectiveDate = $item['hire_date'];
                    $expiryDate = (new \DateTimeImmutable($effectiveDate))
                        ->modify('+' . $months . ' months')
                        ->modify('-1 day')
                        ->format('Y-m-d');

                    $contractCode = $item['contract_code'] ?? ('HDTV-' . $item['employee_code'] . '-' . $item['row']);
                    $contractNumber = $item['contract_number'] ?? $contractCode;
                    $basicSalary = $item['basic_salary'] ?? 0.00;
                    $grossSalary = $item['gross_salary'] ?? $basicSalary;
                    $netSalary = $item['net_salary'] ?? $grossSalary;

                    $this->employees->createContract([
                        'contract_code' => $contractCode,
                        'employee_id' => $employeeId,
                        'contract_type_id' => (int) $contractType['contract_type_id'],
                        'contract_number' => $contractNumber,
                        'sign_date' => $effectiveDate,
                        'effective_date' => $effectiveDate,
                        'expiry_date' => $expiryDate,
                        'position_id' => $item['position_id'],
                        'department_id' => $item['department_id'],
                        'basic_salary' => $basicSalary,
                        'gross_salary' => $grossSalary,
                        'net_salary' => $netSalary,
                        'work_location' => $item['work_location'],
                        'job_title' => $item['job_title'],
                        'status' => 'CÓ_HIỆU_LỰC',
                        'created_by' => $actorId,
                        'updated_by' => $actorId,
                    ]);
                }

                $created[] = [
                    'row' => $item['row'],
                    'employee_id' => $employeeId,
                    'employee_code' => $item['employee_code'],
                    'company_email' => $item['company_email'],
                ];
            }

            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw new HttpException('Import failed: ' . $exception->getMessage(), 422, 'import_error');
        }

        return $this->created([
            'mode' => 'import',
            'total_rows' => count($rows),
            'imported_rows' => count($created),
            'error_rows' => count($errors),
            'created' => $created,
            'errors' => $errors,
        ], 'Probation employees imported');
    }

    private function mapImportRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $header => $value) {
            $normalized[$this->normalizeHeader((string) $header)] = $value;
        }

        $pick = static function (array $source, array $aliases): mixed {
            foreach ($aliases as $alias) {
                if (array_key_exists($alias, $source)) {
                    return $source[$alias];
                }
            }
            return null;
        };

        return [
            'employee_code' => $pick($normalized, ['employee_code', 'employeeid', 'manhanvien', 'ma_nhan_vien', 'ma_nv']),
            'full_name' => $pick($normalized, ['full_name', 'hoten', 'ho_ten', 'ten_nhan_vien']),
            'company_email' => $pick($normalized, ['company_email', 'email_cong_ty', 'email', 'congty_email']),
            'phone_number' => $pick($normalized, ['phone_number', 'so_dien_thoai', 'phone', 'sdt']),
            'hire_date' => $pick($normalized, ['hire_date', 'ngay_vao_lam', 'start_date']),
            'date_of_birth' => $pick($normalized, ['date_of_birth', 'ngay_sinh', 'dob']),
            'gender' => $pick($normalized, ['gender', 'gioi_tinh']),
            'nationality_id' => $pick($normalized, ['nationality_id', 'quoc_tich_id']),
            'department_id' => $pick($normalized, ['department_id', 'phong_ban_id']),
            'position_id' => $pick($normalized, ['position_id', 'chuc_vu_id']),
            'basic_salary' => $pick($normalized, ['basic_salary', 'luong_co_ban']),
            'gross_salary' => $pick($normalized, ['gross_salary', 'luong_gross']),
            'net_salary' => $pick($normalized, ['net_salary', 'luong_net']),
            'probation_months' => $pick($normalized, ['probation_months', 'so_thang_thu_viec', 'thoi_han_thu_viec']),
            'contract_code' => $pick($normalized, ['contract_code', 'ma_hop_dong']),
            'contract_number' => $pick($normalized, ['contract_number', 'so_hop_dong']),
            'job_title' => $pick($normalized, ['job_title', 'chuc_danh']),
            'work_location' => $pick($normalized, ['work_location', 'dia_diem_lam_viec']),
        ];
    }

    private function isImportRowEmpty(array $mapped): bool
    {
        foreach ($mapped as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        $header = $this->removeVietnameseAccents($header);
        $header = strtolower($header);
        $header = preg_replace('/[^a-z0-9]+/', '_', $header) ?? $header;
        return trim($header, '_');
    }

    private function removeVietnameseAccents(string $input): string
    {
        $map = [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'đ' => 'd',
            'À' => 'A', 'Á' => 'A', 'Ạ' => 'A', 'Ả' => 'A', 'Ã' => 'A',
            'Â' => 'A', 'Ầ' => 'A', 'Ấ' => 'A', 'Ậ' => 'A', 'Ẩ' => 'A', 'Ẫ' => 'A',
            'Ă' => 'A', 'Ằ' => 'A', 'Ắ' => 'A', 'Ặ' => 'A', 'Ẳ' => 'A', 'Ẵ' => 'A',
            'È' => 'E', 'É' => 'E', 'Ẹ' => 'E', 'Ẻ' => 'E', 'Ẽ' => 'E',
            'Ê' => 'E', 'Ề' => 'E', 'Ế' => 'E', 'Ệ' => 'E', 'Ể' => 'E', 'Ễ' => 'E',
            'Ì' => 'I', 'Í' => 'I', 'Ị' => 'I', 'Ỉ' => 'I', 'Ĩ' => 'I',
            'Ò' => 'O', 'Ó' => 'O', 'Ọ' => 'O', 'Ỏ' => 'O', 'Õ' => 'O',
            'Ô' => 'O', 'Ồ' => 'O', 'Ố' => 'O', 'Ộ' => 'O', 'Ổ' => 'O', 'Ỗ' => 'O',
            'Ơ' => 'O', 'Ờ' => 'O', 'Ớ' => 'O', 'Ợ' => 'O', 'Ở' => 'O', 'Ỡ' => 'O',
            'Ù' => 'U', 'Ú' => 'U', 'Ụ' => 'U', 'Ủ' => 'U', 'Ũ' => 'U',
            'Ư' => 'U', 'Ừ' => 'U', 'Ứ' => 'U', 'Ự' => 'U', 'Ử' => 'U', 'Ữ' => 'U',
            'Ỳ' => 'Y', 'Ý' => 'Y', 'Ỵ' => 'Y', 'Ỷ' => 'Y', 'Ỹ' => 'Y',
            'Đ' => 'D',
        ];

        return strtr($input, $map);
    }

    private function normalizeDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 1000) {
                $timestamp = (int) round(($numeric - 25569) * 86400);
                if ($timestamp > 0) {
                    return gmdate('Y-m-d', $timestamp);
                }
            }
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private function normalizeGender(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = strtoupper($this->removeVietnameseAccents(trim((string) $value)));
        if (in_array($raw, ['NAM', 'MALE', 'M'], true)) {
            return 'NAM';
        }
        if (in_array($raw, ['NU', 'FEMALE', 'F'], true)) {
            return 'NỮ';
        }
        if (in_array($raw, ['KHAC', 'OTHER', 'O'], true)) {
            return 'KHÁC';
        }
        return null;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT);
        return $filtered === false ? null : (int) $filtered;
    }

    private function toMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = str_replace([',', ' '], '', (string) $value);
        if (!is_numeric($text)) {
            return null;
        }
        return round((float) $text, 2);
    }

    private function toBoolean(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $text = strtolower(trim((string) $value));
        if (in_array($text, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return true;
        }
        if (in_array($text, ['0', 'false', 'no', 'n', 'off'], true)) {
            return false;
        }
        return $default;
    }

    private function nullIfEmpty(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
