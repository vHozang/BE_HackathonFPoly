<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Contract;
use App\Models\ContractType;

class ContractController extends Controller
{
    private Contract $contracts;
    private ContractType $contractTypes;

    public function __construct()
    {
        $this->contracts = new Contract();
        $this->contractTypes = new ContractType();
    }

    public function index(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');
        $employeeId = $request->query('employee_id') !== null ? (int) $request->query('employee_id') : null;

        $result = $this->contracts->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($status) ? $status : null,
            $employeeId
        );

        return $this->ok(
            $result['items'],
            'Contract list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function show(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->contracts->findDetail($id);
        if ($item === null) {
            throw new HttpException('Contract not found', 404, 'not_found');
        }
        return $this->ok($item, 'Contract detail');
    }

    public function store(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'contract_code' => ['string'],
            'employee_id' => ['required', 'integer'],
            'contract_type_id' => ['required', 'integer'],
            'contract_number' => ['string'],
            'sign_date' => ['required', 'date'],
            'effective_date' => ['required', 'date'],
            'expiry_date' => ['date'],
            'position_id' => ['integer'],
            'department_id' => ['integer'],
            'basic_salary' => ['numeric'],
            'gross_salary' => ['numeric'],
            'net_salary' => ['numeric'],
            'work_location' => ['string'],
            'job_title' => ['string'],
            'status' => ['string'],
            'is_renewed' => ['boolean'],
            'renewed_from_contract_id' => ['integer'],
            'termination_reason' => ['string'],
            'termination_date' => ['date'],
        ]);

        if (!isset($payload['contract_code']) || trim((string) $payload['contract_code']) === '') {
            $payload['contract_code'] = $this->generateContractCode();
        }
        if (!isset($payload['contract_number']) || trim((string) $payload['contract_number']) === '') {
            $payload['contract_number'] = (string) $payload['contract_code'];
        }

        $payload['status'] = $this->toDbStatus($payload['status'] ?? null);
        $payload['basic_salary'] = (float) ($payload['basic_salary'] ?? 0);
        $payload['gross_salary'] = (float) ($payload['gross_salary'] ?? $payload['basic_salary']);
        $payload['net_salary'] = isset($payload['net_salary']) ? (float) $payload['net_salary'] : null;

        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 0);
        $payload['created_by'] = $actorId > 0 ? $actorId : null;
        $payload['updated_by'] = $actorId > 0 ? $actorId : null;

        $id = $this->contracts->create($payload);
        $saved = $this->contracts->findDetail($id);

        $action = isset($payload['renewed_from_contract_id']) ? 'GIA_HẠN' : 'TẠO';
        if ($actorId > 0) {
            $this->contracts->createHistory(
                $id,
                $action,
                $actorId,
                null,
                json_encode($saved, JSON_UNESCAPED_UNICODE),
                'Cập nhật từ màn quản lý hợp đồng'
            );
        }

        return $this->created($saved, 'Contract created');
    }

    public function update(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->contracts->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Contract not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'employee_id' => ['integer'],
            'contract_type_id' => ['integer'],
            'contract_number' => ['string'],
            'sign_date' => ['date'],
            'effective_date' => ['date'],
            'expiry_date' => ['date'],
            'position_id' => ['integer'],
            'department_id' => ['integer'],
            'basic_salary' => ['numeric'],
            'gross_salary' => ['numeric'],
            'net_salary' => ['numeric'],
            'work_location' => ['string'],
            'job_title' => ['string'],
            'status' => ['string'],
            'is_renewed' => ['boolean'],
            'renewed_from_contract_id' => ['integer'],
            'termination_reason' => ['string'],
            'termination_date' => ['date'],
        ]);

        if (isset($payload['status'])) {
            $payload['status'] = $this->toDbStatus((string) $payload['status']);
        }
        if (isset($payload['basic_salary'])) {
            $payload['basic_salary'] = (float) $payload['basic_salary'];
        }
        if (isset($payload['gross_salary'])) {
            $payload['gross_salary'] = (float) $payload['gross_salary'];
        }
        if (isset($payload['net_salary'])) {
            $payload['net_salary'] = (float) $payload['net_salary'];
        }

        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 0);
        $payload['updated_by'] = $actorId > 0 ? $actorId : null;

        $this->contracts->updateById($id, $payload);
        $saved = $this->contracts->findDetail($id);

        if ($actorId > 0) {
            $action = (($saved['status'] ?? '') === 'ĐÃ_CHẤM_DỨT') ? 'CHẤM_DỨT' : 'CẬP_NHẬT';
            $this->contracts->createHistory(
                $id,
                $action,
                $actorId,
                json_encode($existing, JSON_UNESCAPED_UNICODE),
                json_encode($saved, JSON_UNESCAPED_UNICODE),
                'Cập nhật từ màn quản lý hợp đồng'
            );
        }

        return $this->ok($saved, 'Contract updated');
    }

    public function contractTypes(Request $request): array
    {
        return $this->ok($this->contractTypes->listActive(), 'Contract type list');
    }

    private function toDbStatus(mixed $value): string
    {
        $status = strtoupper(trim((string) ($value ?? '')));
        return match ($status) {
            'DA_THANH_LY', 'DA_CHAM_DUT', 'TERMINATED' => 'ĐÃ_CHẤM_DỨT',
            'CHO_HIEU_LUC', 'PENDING' => 'CHỜ_HIỆU_LỰC',
            'SAP_HET_HAN', 'HET_HAN', 'EXPIRED' => 'HẾT_HẠN',
            default => 'CÓ_HIỆU_LỰC',
        };
    }

    private function generateContractCode(): string
    {
        return 'HD-' . date('Y') . '-' . random_int(1000, 9999);
    }
}
