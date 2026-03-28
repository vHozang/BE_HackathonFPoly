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
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\Notification;
use App\Models\RequestModel;

class LeaveController extends Controller
{
    private LeaveRequest $leaveRequests;
    private LeaveBalance $leaveBalances;
    private RequestModel $requests;
    private Notification $notifications;

    public function __construct()
    {
        $this->leaveRequests = new LeaveRequest();
        $this->leaveBalances = new LeaveBalance();
        $this->requests = new RequestModel();
        $this->notifications = new Notification();
    }

    public function leaveRequestIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $scopeEmployeeIds = $request->attribute('scope_employee_ids');
        $employeeId = $request->query('employee_id') !== null ? (int) $request->query('employee_id') : null;
        $leaveTypeId = $request->query('leave_type_id') !== null ? (int) $request->query('leave_type_id') : null;
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $status = $request->query('status');
        $employeeIds = is_array($scopeEmployeeIds) ? $scopeEmployeeIds : null;
        if ($employeeId !== null) {
            $employeeIds = [$employeeId];
        }

        $result = $this->leaveRequests->paginateList(
            $paging['offset'],
            $paging['per_page'],
            $employeeIds,
            $leaveTypeId,
            is_string($dateFrom) ? $dateFrom : null,
            is_string($dateTo) ? $dateTo : null,
            is_string($status) ? $this->normalizeRequestStatus($status) : null
        );

        return $this->ok(
            $result['items'],
            'Leave request list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function leaveRequestShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->leaveRequests->find($id);
        if ($item === null) {
            throw new HttpException('Leave request not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $item['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }
        return $this->ok($item, 'Leave request detail');
    }

    public function leaveRequestStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'requester_id' => ['required', 'integer'],
            'request_date' => ['required', 'date'],
            'leave_type_id' => ['required', 'integer'],
            'employee_id' => ['required', 'integer'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date'],
            'number_of_days' => ['required', 'numeric', 'min:0.5'],
            'reason' => ['string'],
            'is_urgent' => ['boolean'],
            'from_session' => ['string'],
            'to_session' => ['string'],
            'leave_used_type' => ['string'],
            'base_days_used' => ['numeric'],
            'seniority_days_used' => ['numeric'],
            'carried_over_days_used' => ['numeric'],
            'paid_days' => ['numeric'],
            'unpaid_days' => ['numeric'],
        ]);

        if ((string) $payload['from_date'] > (string) $payload['to_date']) {
            throw new HttpException('from_date must be <= to_date', 422, 'validation_error');
        }

        $authUser = $request->attribute('auth_user');
        $creatorId = (int) ($authUser['employee_id'] ?? 1);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $requestData = [
                'request_code' => 'LV-' . date('Ymd-His') . '-' . random_int(100, 999),
                'request_type_id' => (int) ($request->input('request_type_id', 1)),
                'requester_id' => (int) $payload['requester_id'],
                'request_date' => $payload['request_date'],
                'from_date' => $payload['from_date'],
                'to_date' => $payload['to_date'],
                'duration' => $payload['number_of_days'],
                'reason' => $payload['reason'] ?? null,
                'is_urgent' => $payload['is_urgent'] ?? 0,
                'status' => $this->normalizeRequestStatus((string) $request->input('status', 'CHO_DUYET')),
                'created_by' => $creatorId,
                'updated_by' => $creatorId,
            ];

            $requestId = $this->requests->create($requestData);

            $leaveData = [
                'request_id' => $requestId,
                'leave_type_id' => (int) $payload['leave_type_id'],
                'employee_id' => (int) $payload['employee_id'],
                'from_date' => $payload['from_date'],
                'to_date' => $payload['to_date'],
                'from_session' => $payload['from_session'] ?? 'CẢ_NGÀY',
                'to_session' => $payload['to_session'] ?? 'CẢ_NGÀY',
                'number_of_days' => $payload['number_of_days'],
                'leave_used_type' => $payload['leave_used_type'] ?? 'BASE',
                'base_days_used' => $payload['base_days_used'] ?? 0,
                'seniority_days_used' => $payload['seniority_days_used'] ?? 0,
                'carried_over_days_used' => $payload['carried_over_days_used'] ?? 0,
                'paid_days' => $payload['paid_days'] ?? $payload['number_of_days'],
                'unpaid_days' => $payload['unpaid_days'] ?? 0,
                'handover_notes' => $request->input('handover_notes'),
                'contact_phone' => $request->input('contact_phone'),
                'emergency_contact' => $request->input('emergency_contact'),
                'attachment_url' => $request->input('attachment_url'),
            ];

            $leaveRequestId = $this->leaveRequests->create($leaveData);

            $this->notifyOnLeaveCreated(
                (int) $payload['employee_id'],
                $requestId,
                (float) $payload['number_of_days'],
                $creatorId
            );

            $db->commit();

            return $this->created(
                [
                    'request' => $this->requests->findDetail($requestId),
                    'leave_request' => $this->leaveRequests->find($leaveRequestId),
                ],
                'Leave request created'
            );
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function leaveRequestUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existingLeave = $this->leaveRequests->find($id);
        if ($existingLeave === null) {
            throw new HttpException('Leave request not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existingLeave['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $payload = Validator::validate($request->all(), [
            'status' => ['string'],
            'reason' => ['string'],
            'notes' => ['string'],
            'rejectionReason' => ['string'],
            'from_date' => ['date'],
            'to_date' => ['date'],
            'startDate' => ['date'],
            'endDate' => ['date'],
            'number_of_days' => ['numeric', 'min:0.5'],
            'days' => ['numeric', 'min:0.5'],
            'completed_date' => ['date'],
        ]);

        $currentRequest = $this->requests->findDetail((int) $existingLeave['request_id']);
        if ($currentRequest === null) {
            throw new HttpException('Request not found', 404, 'not_found');
        }
        $currentStatus = (string) ($currentRequest['status'] ?? 'CHỜ_DUYỆT');
        $previousStatus = $currentStatus;
        $leaveDays = isset($payload['number_of_days']) || isset($payload['days'])
            ? (float) ($payload['number_of_days'] ?? $payload['days'])
            : (float) ($existingLeave['number_of_days'] ?? 0);
        $roleCodes = $this->extractRoleCodes($authUser);

        $requestPayload = [];
        if (isset($payload['status'])) {
            $incomingStatus = $this->normalizeRequestStatus((string) $payload['status']);

            if ($incomingStatus === 'TỪ_CHỐI') {
                $requestPayload['status'] = 'TỪ_CHỐI';
            } elseif (in_array($incomingStatus, ['ĐÃ_DUYỆT', 'ĐANG_XỬ_LÝ', 'CHỜ_XÁC_NHẬN_HR', 'CHỜ_GIÁM_ĐỐC_DUYỆT'], true)) {
                $resolvedStatus = $this->resolveLeaveApprovalStatus($roleCodes, $currentStatus, $leaveDays);
                if ($resolvedStatus === null) {
                    throw new HttpException('Invalid approval step for current role/status', 422, 'validation_error');
                }
                $requestPayload['status'] = $resolvedStatus;
            } else {
                $requestPayload['status'] = $incomingStatus;
            }

            if (in_array($requestPayload['status'], ['ĐÃ_DUYỆT', 'TỪ_CHỐI', 'HOÀN_THÀNH', 'ĐÃ_HỦY'], true) && !isset($payload['completed_date'])) {
                $requestPayload['completed_date'] = date('Y-m-d H:i:s');
            }
        }

        if (isset($payload['reason'])) {
            $requestPayload['reason'] = $payload['reason'];
        }
        if (isset($payload['notes'])) {
            $requestPayload['notes'] = $payload['notes'];
        }
        if (isset($payload['rejectionReason'])) {
            $requestPayload['notes'] = $payload['rejectionReason'];
        }
        if (isset($payload['completed_date'])) {
            $requestPayload['completed_date'] = $payload['completed_date'];
        }

        $fromDate = $payload['from_date'] ?? $payload['startDate'] ?? null;
        $toDate = $payload['to_date'] ?? $payload['endDate'] ?? null;
        $days = $payload['number_of_days'] ?? $payload['days'] ?? null;

        if ($fromDate !== null && $toDate !== null && (string) $fromDate > (string) $toDate) {
            throw new HttpException('from_date must be <= to_date', 422, 'validation_error');
        }

        $leavePayload = [];
        if ($fromDate !== null) {
            $leavePayload['from_date'] = $fromDate;
            $requestPayload['from_date'] = $fromDate;
        }
        if ($toDate !== null) {
            $leavePayload['to_date'] = $toDate;
            $requestPayload['to_date'] = $toDate;
        }
        if ($days !== null) {
            $leavePayload['number_of_days'] = $days;
            $leavePayload['paid_days'] = $days;
            $requestPayload['duration'] = $days;
        }

        $actorId = (int) ($authUser['employee_id'] ?? 1);
        $requestPayload['updated_by'] = $actorId;

        $db = Database::connection();
        $db->beginTransaction();
        try {
            if ($requestPayload !== []) {
                $this->requests->updateById((int) $existingLeave['request_id'], $requestPayload);
            }
            if ($leavePayload !== []) {
                $this->leaveRequests->updateById($id, $leavePayload);
            }

            $db->commit();
            $newStatus = (string) ($requestPayload['status'] ?? $previousStatus);
            if ($newStatus !== $previousStatus) {
                $this->notifyOnLeaveWorkflowTransition(
                    (int) $existingLeave['employee_id'],
                    (int) $existingLeave['request_id'],
                    $previousStatus,
                    $newStatus,
                    $leaveDays,
                    $actorId
                );
            }
            $updated = $this->leaveRequests->find($id);
            if ($updated !== null) {
                $updatedRequest = $this->requests->findDetail((int) $existingLeave['request_id']);
                if ($updatedRequest !== null) {
                    $updated['status'] = $updatedRequest['status'] ?? null;
                    $updated['request_status'] = $updatedRequest['status'] ?? null;
                }
            }
            return $this->ok($updated, 'Leave request updated');
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function leaveRequestDelete(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existingLeave = $this->leaveRequests->find($id);
        if ($existingLeave === null) {
            throw new HttpException('Leave request not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existingLeave['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $this->leaveRequests->deleteById($id);
            $this->requests->deleteById((int) $existingLeave['request_id']);
            $db->commit();
            return $this->ok(null, 'Leave request deleted');
        } catch (\Throwable $exception) {
            $db->rollBack();
            throw $exception;
        }
    }

    public function leaveBalanceIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $scopeEmployeeIds = $request->attribute('scope_employee_ids');
        $employeeId = $request->query('employee_id') !== null ? (int) $request->query('employee_id') : null;
        $year = $request->query('year') !== null ? (int) $request->query('year') : null;
        $employeeIds = is_array($scopeEmployeeIds) ? $scopeEmployeeIds : null;
        if ($employeeId !== null) {
            $employeeIds = [$employeeId];
        }

        $result = $this->leaveBalances->paginateList(
            $paging['offset'],
            $paging['per_page'],
            $employeeIds,
            $year
        );

        return $this->ok(
            $result['items'],
            'Leave balance list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function leaveBalanceShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->leaveBalances->find($id);
        if ($item === null) {
            throw new HttpException('Leave balance not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $item['employee_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }
        return $this->ok($item, 'Leave balance detail');
    }

    private function normalizeRequestStatus(string $status): string
    {
        $normalized = strtoupper(trim($status));
        $normalized = str_replace(' ', '_', $normalized);

        return match ($normalized) {
            'NHAP', 'DRAFT' => 'NHÁP',
            'CHO_DUYET', 'CHỜ_DUYỆT', 'PENDING' => 'CHỜ_DUYỆT',
            'CHO_GIAM_DOC_DUYET', 'CHỜ_GIÁM_ĐỐC_DUYỆT', 'WAIT_DIRECTOR', 'PENDING_DIRECTOR' => 'CHỜ_GIÁM_ĐỐC_DUYỆT',
            'CHO_XAC_NHAN_HR', 'CHỜ_XÁC_NHẬN_HR', 'WAIT_HR_CONFIRM', 'WAIT_HR' => 'CHỜ_XÁC_NHẬN_HR',
            'DANG_XU_LY', 'ĐANG_XỬ_LÝ', 'IN_PROGRESS' => 'ĐANG_XỬ_LÝ',
            'DA_DUYET', 'ĐÃ_DUYỆT', 'APPROVED' => 'ĐÃ_DUYỆT',
            'TU_CHOI', 'TỪ_CHỐI', 'REJECTED' => 'TỪ_CHỐI',
            'DA_HUY', 'ĐÃ_HỦY', 'CANCELED', 'CANCELLED' => 'ĐÃ_HỦY',
            'HOAN_THANH', 'HOÀN_THÀNH', 'DONE', 'COMPLETED' => 'HOÀN_THÀNH',
            default => 'CHỜ_DUYỆT',
        };
    }

    private function extractRoleCodes(array $authUser): array
    {
        $roles = $authUser['roles'] ?? [];
        $codes = [];
        foreach ($roles as $role) {
            $code = is_array($role) ? ($role['role_code'] ?? '') : $role;
            $code = strtoupper((string) $code);
            if ($code !== '') {
                $codes[] = $code;
            }
        }
        return array_values(array_unique($codes));
    }

    private function resolveLeaveApprovalStatus(array $roleCodes, string $currentStatus, float $leaveDays): ?string
    {
        $isManager = in_array('MANAGER', $roleCodes, true) || in_array('ADMIN', $roleCodes, true);
        $isDirector = in_array('DIRECTOR', $roleCodes, true) || in_array('ADMIN', $roleCodes, true);
        $isHr = in_array('HR', $roleCodes, true) || in_array('ADMIN', $roleCodes, true);

        if ($isManager && in_array($currentStatus, ['CHỜ_DUYỆT', 'ĐANG_XỬ_LÝ'], true)) {
            return $leaveDays > 7 ? 'CHỜ_GIÁM_ĐỐC_DUYỆT' : 'CHỜ_XÁC_NHẬN_HR';
        }

        if ($isDirector && $currentStatus === 'CHỜ_GIÁM_ĐỐC_DUYỆT') {
            return 'CHỜ_XÁC_NHẬN_HR';
        }

        if ($isHr && $currentStatus === 'CHỜ_XÁC_NHẬN_HR') {
            return 'ĐÃ_DUYỆT';
        }

        return null;
    }

    private function notifyOnLeaveCreated(int $employeeId, int $requestId, float $leaveDays, int $actorId): void
    {
        $managerId = $this->findDepartmentManagerIdByEmployee($employeeId);
        $hrIds = $this->findEmployeeIdsByRoleCodes(['HR']);
        $targets = array_values(array_unique(array_filter(array_merge($hrIds, $managerId ? [$managerId] : []))));

        foreach ($targets as $targetId) {
            if ($targetId === $employeeId) {
                continue;
            }
            $this->createWorkflowNotification(
                $targetId,
                'Đơn nghỉ phép mới cần duyệt',
                sprintf('Có đơn nghỉ phép %.1f ngày từ nhân viên #%d đang chờ Trưởng phòng xử lý.', $leaveDays, $employeeId),
                $actorId,
                $requestId
            );
        }
    }

    private function notifyOnLeaveWorkflowTransition(
        int $employeeId,
        int $requestId,
        string $oldStatus,
        string $newStatus,
        float $leaveDays,
        int $actorId
    ): void {
        if ($newStatus === 'CHỜ_GIÁM_ĐỐC_DUYỆT') {
            $directorIds = $this->findEmployeeIdsByRoleCodes(['DIRECTOR', 'ADMIN']);
            $hrIds = $this->findEmployeeIdsByRoleCodes(['HR']);
            $targets = array_values(array_unique(array_filter(array_merge($directorIds, $hrIds))));
            foreach ($targets as $targetId) {
                if ($targetId === $employeeId) {
                    continue;
                }
                $this->createWorkflowNotification(
                    $targetId,
                    'Đơn nghỉ phép chờ Giám đốc duyệt',
                    sprintf('Đơn nghỉ phép %.1f ngày của nhân viên #%d đã được Trưởng phòng duyệt và đang chờ Giám đốc.', $leaveDays, $employeeId),
                    $actorId,
                    $requestId
                );
            }
            return;
        }

        if ($newStatus === 'CHỜ_XÁC_NHẬN_HR') {
            $hrIds = $this->findEmployeeIdsByRoleCodes(['HR']);
            foreach ($hrIds as $targetId) {
                if ($targetId === $employeeId) {
                    continue;
                }
                $this->createWorkflowNotification(
                    $targetId,
                    'Đơn nghỉ phép chờ HR xác nhận',
                    sprintf('Đơn nghỉ phép của nhân viên #%d đã được duyệt, vui lòng xác nhận và chấm công nghỉ phép.', $employeeId),
                    $actorId,
                    $requestId
                );
            }
            return;
        }

        if ($newStatus === 'ĐÃ_DUYỆT') {
            $this->createWorkflowNotification(
                $employeeId,
                'Đơn nghỉ phép đã duyệt thành công',
                'Đơn nghỉ phép của bạn đã được phê duyệt hoàn tất. HR sẽ thực hiện chấm công nghỉ phép.',
                $actorId,
                $requestId
            );
            return;
        }

        if ($newStatus === 'TỪ_CHỐI' && $oldStatus !== 'TỪ_CHỐI') {
            $this->createWorkflowNotification(
                $employeeId,
                'Đơn nghỉ phép bị từ chối',
                'Đơn nghỉ phép của bạn đã bị từ chối. Vui lòng kiểm tra ghi chú phản hồi.',
                $actorId,
                $requestId
            );
        }
    }

    private function createWorkflowNotification(
        int $receiverId,
        string $title,
        string $content,
        int $senderId,
        int $requestId
    ): void {
        if ($receiverId <= 0) {
            return;
        }

        try {
            $this->notifications->create([
                'notification_type' => 'LEAVE_WORKFLOW',
                'title' => $title,
                'content' => $content,
                'sender_id' => $senderId > 0 ? $senderId : null,
                'receiver_id' => $receiverId,
                'is_read' => 0,
                'priority' => 'TRUNG_BÌNH',
                'reference_type' => 'LEAVE_REQUEST',
                'reference_id' => $requestId,
                'action_url' => '/admin/hr/nghi-phep',
            ]);
        } catch (\Throwable) {
            // Best-effort notification, should not block workflow
        }
    }

    private function findDepartmentManagerIdByEmployee(int $employeeId): ?int
    {
        $sql = "SELECT d.manager_id
                FROM employment_histories eh
                JOIN departments d ON d.department_id = eh.department_id
                WHERE eh.employee_id = :employee_id
                  AND eh.is_current = 1
                ORDER BY eh.history_id DESC
                LIMIT 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['employee_id' => $employeeId]);
        $managerId = (int) ($stmt->fetch()['manager_id'] ?? 0);
        return $managerId > 0 ? $managerId : null;
    }

    /**
     * @param array<int, string> $roleCodes
     * @return array<int, int>
     */
    private function findEmployeeIdsByRoleCodes(array $roleCodes): array
    {
        if ($roleCodes === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleCodes), '?'));
        $sql = "SELECT DISTINCT er.employee_id
                FROM employee_roles er
                JOIN roles r ON r.role_id = er.role_id
                WHERE r.role_code IN ($placeholders)
                  AND er.is_active = 1
                  AND (er.expiry_date IS NULL OR er.expiry_date >= CURDATE())";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_values($roleCodes));
        $rows = $stmt->fetchAll() ?: [];
        return array_values(array_unique(array_map(static fn(array $row): int => (int) ($row['employee_id'] ?? 0), $rows)));
    }
}
