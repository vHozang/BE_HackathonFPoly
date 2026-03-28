<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Hierarchy;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Attendance;
use App\Models\OvertimeRequest;
use App\Models\RequestModel;

class AttendanceController extends Controller
{
    private Attendance $attendances;
    private OvertimeRequest $overtimes;
    private RequestModel $requests;

    public function __construct()
    {
        $this->attendances = new Attendance();
        $this->overtimes = new OvertimeRequest();
        $this->requests = new RequestModel();
    }

    public function attendanceIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $scopeIds = $request->attribute('scope_employee_ids');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $result = $this->attendances->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_array($scopeIds) ? $scopeIds : null,
            is_string($dateFrom) ? $dateFrom : null,
            is_string($dateTo) ? $dateTo : null,
            is_string($status) ? $status : null
        );

        return $this->ok(
            $result['items'],
            'Attendance list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function attendanceShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->attendances->findDetail($id);
        if ($item === null) {
            throw new HttpException('Attendance not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $item['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        return $this->ok($item, 'Attendance detail');
    }

    public function attendanceStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'employee_id' => ['integer'],
            'attendance_date' => ['required', 'date'],
            'shift_type_id' => ['integer'],
            'check_in_time' => ['date'],
            'check_out_time' => ['date'],
            'check_in_method' => ['string'],
            'check_out_method' => ['string'],
            'work_type' => ['string'],
            'actual_working_hours' => ['numeric'],
            'overtime_hours' => ['numeric'],
            'late_minutes' => ['integer'],
            'early_leave_minutes' => ['integer'],
            'is_holiday' => ['boolean'],
            'is_overtime' => ['boolean'],
            'status' => ['string'],
            'notes' => ['string'],
        ]);

        $authUser = $request->attribute('auth_user');
        $employeeId = (int) ($payload['employee_id'] ?? $request->attribute('forced_employee_id') ?? ($authUser['employee_id'] ?? 0));
        if ($employeeId <= 0) {
            throw new HttpException('employee_id is required', 422, 'validation_error');
        }
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, $employeeId, true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $payload['employee_id'] = $employeeId;
        $id = $this->attendances->create($payload);
        return $this->created($this->attendances->findDetail($id), 'Attendance created');
    }

    public function attendanceUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->attendances->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Attendance not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existing['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $payload = Validator::validate($request->all(), [
            'shift_type_id' => ['integer'],
            'check_in_time' => ['date'],
            'check_out_time' => ['date'],
            'check_in_method' => ['string'],
            'check_out_method' => ['string'],
            'work_type' => ['string'],
            'actual_working_hours' => ['numeric'],
            'overtime_hours' => ['numeric'],
            'late_minutes' => ['integer'],
            'early_leave_minutes' => ['integer'],
            'is_holiday' => ['boolean'],
            'is_overtime' => ['boolean'],
            'status' => ['string'],
            'notes' => ['string'],
            'approved_by' => ['integer'],
            'approved_date' => ['date'],
        ]);

        $this->attendances->updateById($id, $payload);
        return $this->ok($this->attendances->findDetail($id), 'Attendance updated');
    }

    public function attendanceDelete(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->attendances->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Attendance not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existing['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $this->attendances->deleteById($id);
        return $this->ok(null, 'Attendance deleted');
    }

    public function overtimeIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $scopeIds = $request->attribute('scope_employee_ids');
        $status = $request->query('status');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $result = $this->overtimes->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_array($scopeIds) ? $scopeIds : null,
            is_string($dateFrom) ? $dateFrom : null,
            is_string($dateTo) ? $dateTo : null,
            is_string($status) ? $status : null
        );
        return $this->ok(
            $result['items'],
            'Overtime request list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function overtimeShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->overtimes->findDetail($id);
        if ($item === null) {
            throw new HttpException('Overtime request not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $item['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }
        return $this->ok($item, 'Overtime request detail');
    }

    public function overtimeStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'employee_id' => ['integer'],
            'overtime_date' => ['required', 'date'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'break_time' => ['integer', 'min:0'],
            'reason' => ['string'],
            'status' => ['string'],
            'request_type_id' => ['integer'],
        ]);

        $authUser = $request->attribute('auth_user');
        $employeeId = (int) ($payload['employee_id'] ?? $request->attribute('forced_employee_id') ?? ($authUser['employee_id'] ?? 0));
        if ($employeeId <= 0) {
            throw new HttpException('employee_id is required', 422, 'validation_error');
        }
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, $employeeId, true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $requestId = (int) ($request->input('request_id') ?? 0);
            if ($requestId <= 0) {
                $requestPayload = [
                    'request_code' => 'OT-' . date('Ymd-His') . '-' . random_int(100, 999),
                    'request_type_id' => (int) ($payload['request_type_id'] ?? 3),
                    'requester_id' => $employeeId,
                    'request_date' => date('Y-m-d'),
                    'from_date' => $payload['overtime_date'] . ' 00:00:00',
                    'to_date' => $payload['overtime_date'] . ' 23:59:59',
                    'duration' => null,
                    'reason' => $payload['reason'] ?? null,
                    'status' => 'CHỜ_DUYỆT',
                    'created_by' => (int) ($authUser['employee_id'] ?? 1),
                    'updated_by' => (int) ($authUser['employee_id'] ?? 1),
                ];
                $requestId = $this->requests->create($requestPayload);
            }

            $otPayload = [
                'request_id' => $requestId,
                'employee_id' => $employeeId,
                'overtime_date' => $payload['overtime_date'],
                'start_time' => $payload['start_time'],
                'end_time' => $payload['end_time'],
                'break_time' => $payload['break_time'] ?? 0,
                'reason' => $payload['reason'] ?? null,
                'status' => $payload['status'] ?? 'CHỜ_DUYỆT',
            ];
            $id = $this->overtimes->create($otPayload);
            $db->commit();
            return $this->created($this->overtimes->findDetail($id), 'Overtime request created');
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function overtimeUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->overtimes->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Overtime request not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existing['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $payload = Validator::validate($request->all(), [
            'overtime_date' => ['date'],
            'start_time' => ['string'],
            'end_time' => ['string'],
            'break_time' => ['integer', 'min:0'],
            'reason' => ['string'],
            'approved_by' => ['integer'],
            'approved_date' => ['date'],
            'status' => ['string'],
        ]);
        $this->overtimes->updateById($id, $payload);
        return $this->ok($this->overtimes->findDetail($id), 'Overtime request updated');
    }

    public function overtimeDelete(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->overtimes->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Overtime request not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existing['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }
        $this->overtimes->deleteById($id);
        return $this->ok(null, 'Overtime request deleted');
    }
}
