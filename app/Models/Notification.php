<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Notification extends Model
{
    protected string $table = 'notifications';
    protected string $primaryKey = 'notification_id';
    protected array $fillable = [
        'notification_type',
        'title',
        'content',
        'sender_id',
        'receiver_id',
        'department_id',
        'is_read',
        'read_date',
        'priority',
        'reference_type',
        'reference_id',
        'action_url',
        'expires_at',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?int $receiverId = null,
        ?bool $isRead = null
    ): array {
        $where = [];
        $params = [];

        if ($receiverId !== null) {
            $where[] = 'n.receiver_id = :receiver_id';
            $params['receiver_id'] = $receiverId;
        }
        if ($isRead !== null) {
            $where[] = 'n.is_read = :is_read';
            $params['is_read'] = $isRead ? 1 : 0;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT n.*,
                       s.full_name AS sender_name,
                       r.full_name AS receiver_name
                FROM notifications n
                LEFT JOIN employees s ON s.employee_id = n.sender_id
                LEFT JOIN employees r ON r.employee_id = n.receiver_id
                $whereSql
                ORDER BY n.notification_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total
                     FROM notifications n
                     $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }

    public function findDetail(int $id): ?array
    {
        $sql = "SELECT n.*,
                       s.full_name AS sender_name,
                       r.full_name AS receiver_name
                FROM notifications n
                LEFT JOIN employees s ON s.employee_id = n.sender_id
                LEFT JOIN employees r ON r.employee_id = n.receiver_id
                WHERE n.notification_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
