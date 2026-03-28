<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\ServiceCategory;
use App\Models\ServiceTicket;

class InternalServiceController extends Controller
{
    private ServiceCategory $categories;
    private ServiceTicket $tickets;

    public function __construct()
    {
        $this->categories = new ServiceCategory();
        $this->tickets = new ServiceTicket();
    }

    public function categoryIndex(Request $request): array
    {
        return $this->ok($this->categories->listActive(), 'Service category list');
    }

    public function ticketIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');
        $requesterId = $request->query('requester_id') !== null ? (int) $request->query('requester_id') : null;

        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 0);
        if (!Auth::isPrivileged($authUser)) {
            $requesterId = $actorId;
        }

        $result = $this->tickets->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($status) ? $this->normalizeStatus($status) : null,
            $requesterId
        );

        return $this->ok(
            $result['items'],
            'Service ticket list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function ticketStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'category_id' => ['required', 'integer'],
            'title' => ['required', 'string'],
            'description' => ['string'],
            'priority' => ['string'],
            'requester_id' => ['integer'],
        ]);

        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 0);

        if (!Auth::isPrivileged($authUser)) {
            $payload['requester_id'] = $actorId;
        } elseif (!isset($payload['requester_id']) || (int) $payload['requester_id'] <= 0) {
            $payload['requester_id'] = $actorId;
        }

        if ((int) ($payload['requester_id'] ?? 0) <= 0) {
            throw new HttpException('requester_id is required', 422, 'validation_error');
        }

        $payload['ticket_code'] = 'TKT-' . date('Ymd') . '-' . random_int(1000, 9999);
        $payload['priority'] = $this->normalizePriority($payload['priority'] ?? null);
        $payload['status'] = 'OPEN';

        $id = $this->tickets->create($payload);
        $this->tickets->createUpdateLog(
            $id,
            'SYSTEM',
            null,
            'OPEN',
            'Tạo ticket mới',
            $actorId > 0 ? $actorId : null
        );

        return $this->created($this->tickets->findDetail($id), 'Service ticket created');
    }

    public function ticketUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->tickets->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Service ticket not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 0);
        if (!Auth::isPrivileged($authUser) && (int) $existing['requester_id'] !== $actorId) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        $payload = Validator::validate($request->all(), [
            'title' => ['string'],
            'description' => ['string'],
            'priority' => ['string'],
            'status' => ['string'],
            'assigned_to' => ['integer'],
        ]);

        if (isset($payload['priority'])) {
            $payload['priority'] = $this->normalizePriority((string) $payload['priority']);
        }
        if (isset($payload['status'])) {
            $payload['status'] = $this->normalizeStatus((string) $payload['status']);
            if ($payload['status'] === 'RESOLVED' || $payload['status'] === 'CLOSED') {
                $payload['resolved_at'] = date('Y-m-d H:i:s');
            }
        }

        $this->tickets->updateById($id, $payload);
        $saved = $this->tickets->findDetail($id);

        if (isset($payload['status']) && $payload['status'] !== ($existing['status'] ?? null)) {
            $this->tickets->createUpdateLog(
                $id,
                'STATUS_CHANGE',
                (string) ($existing['status'] ?? ''),
                (string) ($payload['status'] ?? ''),
                'Cập nhật trạng thái ticket',
                $actorId > 0 ? $actorId : null
            );
        }

        return $this->ok($saved, 'Service ticket updated');
    }

    private function normalizePriority(mixed $priority): string
    {
        $value = strtoupper(trim((string) ($priority ?? 'MEDIUM')));
        return in_array($value, ['LOW', 'MEDIUM', 'HIGH', 'URGENT'], true) ? $value : 'MEDIUM';
    }

    private function normalizeStatus(string $status): string
    {
        $value = strtoupper(trim($status));
        return in_array($value, ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED'], true) ? $value : 'OPEN';
    }
}
