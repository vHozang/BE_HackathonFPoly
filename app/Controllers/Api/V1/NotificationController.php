<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Notification;

class NotificationController extends Controller
{
    private Notification $notifications;

    public function __construct()
    {
        $this->notifications = new Notification();
    }

    public function index(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 0);
        if ($actorId <= 0) {
            throw new HttpException('Unauthorized', 401, 'unauthorized');
        }

        $receiverId = $request->query('receiver_id') !== null
            ? (int) $request->query('receiver_id')
            : ((int) ($request->query('userId') ?? 0));
        if ($receiverId <= 0) {
            $receiverId = $actorId;
        }

        // Resilient scope handling: for non-privileged users, silently force self scope
        // instead of throwing 403 when FE sends stale receiver_id.
        if (!Auth::isPrivileged($authUser) && $receiverId !== $actorId) {
            $receiverId = $actorId;
        }

        $isRead = null;
        if ($request->query('is_read') !== null) {
            $isRead = ((string) $request->query('is_read')) === '1';
        } elseif ($request->query('isRead') !== null) {
            $isRead = ((string) $request->query('isRead')) === 'true';
        }

        $result = $this->notifications->paginateList(
            $paging['offset'],
            $paging['per_page'],
            $receiverId,
            $isRead
        );

        return $this->ok(
            $result['items'],
            'Notification list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function store(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'notification_type' => ['string'],
            'type' => ['string'],
            'title' => ['required', 'string'],
            'content' => ['string'],
            'desc' => ['string'],
            'sender_id' => ['integer'],
            'receiver_id' => ['integer'],
            'userId' => ['integer'],
            'department_id' => ['integer'],
            'is_read' => ['boolean'],
            'isRead' => ['boolean'],
            'priority' => ['string'],
            'reference_type' => ['string'],
            'reference_id' => ['integer'],
            'action_url' => ['string'],
            'expires_at' => ['date'],
        ]);

        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 0);

        $receiverId = (int) ($payload['receiver_id'] ?? $payload['userId'] ?? 0);
        if ($receiverId <= 0) {
            throw new HttpException('receiver_id is required', 422, 'validation_error');
        }

        $priorityRaw = strtoupper(trim((string) ($payload['priority'] ?? 'TRUNG_BINH')));
        $priority = match ($priorityRaw) {
            'CAO', 'HIGH', 'DANGER', 'WARNING' => 'CAO',
            'THAP', 'LOW' => 'THẤP',
            default => 'TRUNG_BÌNH',
        };

        $notificationType = trim((string) ($payload['notification_type'] ?? $payload['type'] ?? 'SYSTEM'));
        if ($notificationType === '') {
            $notificationType = 'SYSTEM';
        }

        $content = trim((string) ($payload['content'] ?? $payload['desc'] ?? ''));
        if ($content === '') {
            $content = 'Notification';
        }

        $data = [
            'notification_type' => $notificationType,
            'title' => (string) $payload['title'],
            'content' => $content,
            'sender_id' => (int) ($payload['sender_id'] ?? $actorId) > 0 ? (int) ($payload['sender_id'] ?? $actorId) : null,
            'receiver_id' => $receiverId,
            'department_id' => isset($payload['department_id']) ? (int) $payload['department_id'] : null,
            'is_read' => (bool) ($payload['is_read'] ?? $payload['isRead'] ?? false),
            'priority' => $priority,
            'reference_type' => $payload['reference_type'] ?? null,
            'reference_id' => isset($payload['reference_id']) ? (int) $payload['reference_id'] : null,
            'action_url' => $payload['action_url'] ?? null,
            'expires_at' => $payload['expires_at'] ?? null,
        ];

        if ($data['is_read']) {
            $data['read_date'] = date('Y-m-d H:i:s');
        }

        $id = $this->notifications->create($data);
        return $this->created($this->notifications->findDetail($id), 'Notification created');
    }

    public function update(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->notifications->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Notification not found', 404, 'not_found');
        }

        $authUser = $request->attribute('auth_user');
        $actorId = (int) ($authUser['employee_id'] ?? 0);
        $isPrivileged = Auth::isPrivileged($authUser);
        if (!$isPrivileged && (int) ($existing['receiver_id'] ?? 0) !== $actorId) {
            throw new HttpException('Notification scope denied', 403, 'forbidden');
        }

        $payload = Validator::validate($request->all(), [
            'is_read' => ['boolean'],
            'isRead' => ['boolean'],
            'read_date' => ['date'],
            'title' => ['string'],
            'content' => ['string'],
            'desc' => ['string'],
            'priority' => ['string'],
        ]);

        $update = [];
        if (array_key_exists('is_read', $payload) || array_key_exists('isRead', $payload)) {
            $isRead = (bool) ($payload['is_read'] ?? $payload['isRead']);
            $update['is_read'] = $isRead;
            $update['read_date'] = $isRead ? date('Y-m-d H:i:s') : null;
        }
        if (isset($payload['title'])) {
            $update['title'] = $payload['title'];
        }
        if (isset($payload['content']) || isset($payload['desc'])) {
            $update['content'] = $payload['content'] ?? $payload['desc'];
        }
        if (isset($payload['priority'])) {
            $priorityRaw = strtoupper(trim((string) $payload['priority']));
            $update['priority'] = match ($priorityRaw) {
                'CAO', 'HIGH', 'DANGER', 'WARNING' => 'CAO',
                'THAP', 'LOW' => 'THẤP',
                default => 'TRUNG_BÌNH',
            };
        }
        if (isset($payload['read_date'])) {
            $update['read_date'] = $payload['read_date'];
        }

        $this->notifications->updateById($id, $update);
        return $this->ok($this->notifications->findDetail($id), 'Notification updated');
    }
}
