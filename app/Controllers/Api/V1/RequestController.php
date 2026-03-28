<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Hierarchy;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\RequestModel;

class RequestController extends Controller
{
    private RequestModel $requests;

    public function __construct()
    {
        $this->requests = new RequestModel();
    }

    public function index(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');
        $requesterId = $request->query('requester_id') !== null ? (int) $request->query('requester_id') : null;
        $requestTypeId = $request->query('request_type_id') !== null ? (int) $request->query('request_type_id') : null;
        $scopeIds = $request->attribute('scope_employee_ids');
        $requesterIds = is_array($scopeIds) ? $scopeIds : null;
        if ($requesterId !== null) {
            $requesterIds = [$requesterId];
        }

        $result = $this->requests->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($status) ? $this->normalizeRequestStatus($status) : null,
            $requesterIds,
            $requestTypeId
        );

        return $this->ok(
            $result['items'],
            'Request list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function show(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->requests->findDetail($id);
        if ($item === null) {
            throw new HttpException('Request not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $item['requester_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }
        return $this->ok($item, 'Request detail');
    }

    public function store(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'request_type_id' => ['required', 'integer'],
            'requester_id' => ['integer'],
            'request_date' => ['required', 'date'],
            'from_date' => ['date'],
            'to_date' => ['date'],
            'duration' => ['numeric'],
            'reason' => ['string'],
            'status' => ['string'],
            'current_step_id' => ['integer'],
            'is_urgent' => ['boolean'],
            'attachments' => ['string'],
            'notes' => ['string'],
        ]);

        if (!isset($payload['request_code'])) {
            $payload['request_code'] = 'REQ-' . date('Ymd-His') . '-' . random_int(100, 999);
        }

        if (isset($payload['status'])) {
            $payload['status'] = $this->normalizeRequestStatus((string) $payload['status']);
        }

        $authUser = $request->attribute('auth_user');
        $payload['requester_id'] = (int) ($payload['requester_id'] ?? $request->attribute('forced_employee_id') ?? ($authUser['employee_id'] ?? 0));
        if ($payload['requester_id'] <= 0) {
            throw new HttpException('requester_id is required', 422, 'validation_error');
        }

        $payload['created_by'] = (int) ($authUser['employee_id'] ?? 1);
        $payload['updated_by'] = (int) ($authUser['employee_id'] ?? 1);

        $id = $this->requests->create($payload);
        return $this->created($this->requests->findDetail($id), 'Request created');
    }

    public function update(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->requests->find($id);
        if ($existing === null) {
            throw new HttpException('Request not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existing['requester_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $payload = Validator::validate($request->all(), [
            'from_date' => ['date'],
            'to_date' => ['date'],
            'duration' => ['numeric'],
            'reason' => ['string'],
            'status' => ['string'],
            'current_step_id' => ['integer'],
            'is_urgent' => ['boolean'],
            'attachments' => ['string'],
            'notes' => ['string'],
            'completed_date' => ['date'],
        ]);

        if (isset($payload['status'])) {
            $payload['status'] = $this->normalizeRequestStatus((string) $payload['status']);
        }

        $payload['updated_by'] = (int) ($authUser['employee_id'] ?? 1);

        $this->requests->updateById($id, $payload);
        return $this->ok($this->requests->findDetail($id), 'Request updated');
    }

    public function destroy(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->requests->find($id);
        if ($existing === null) {
            throw new HttpException('Request not found', 404, 'not_found');
        }
        $authUser = $request->attribute('auth_user');
        if (!Auth::isPrivileged($authUser) && !Hierarchy::canAccessEmployee($authUser, (int) $existing['requester_id'], true)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }
        $this->requests->deleteById($id);
        return $this->ok(null, 'Request deleted');
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
}
