<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class ServiceTicket extends Model
{
    protected string $table = 'service_tickets';
    protected string $primaryKey = 'ticket_id';
    protected array $fillable = [
        'ticket_code',
        'requester_id',
        'category_id',
        'title',
        'description',
        'priority',
        'status',
        'assigned_to',
        'resolved_at',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?string $search = null,
        ?string $status = null,
        ?int $requesterId = null
    ): array {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(t.ticket_code LIKE :search OR t.title LIKE :search OR t.description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== null && $status !== '') {
            $where[] = 't.status = :status';
            $params['status'] = $status;
        }
        if ($requesterId !== null) {
            $where[] = 't.requester_id = :requester_id';
            $params['requester_id'] = $requesterId;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT t.*,
                       c.category_name,
                       req.full_name AS requester_name,
                       ass.full_name AS assignee_name
                FROM service_tickets t
                JOIN service_categories c ON c.category_id = t.category_id
                JOIN employees req ON req.employee_id = t.requester_id
                LEFT JOIN employees ass ON ass.employee_id = t.assigned_to
                $whereSql
                ORDER BY t.ticket_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total
                     FROM service_tickets t
                     JOIN service_categories c ON c.category_id = t.category_id
                     JOIN employees req ON req.employee_id = t.requester_id
                     LEFT JOIN employees ass ON ass.employee_id = t.assigned_to
                     $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }

    public function findDetail(int $id): ?array
    {
        $sql = "SELECT t.*,
                       c.category_name,
                       req.full_name AS requester_name,
                       ass.full_name AS assignee_name
                FROM service_tickets t
                JOIN service_categories c ON c.category_id = t.category_id
                JOIN employees req ON req.employee_id = t.requester_id
                LEFT JOIN employees ass ON ass.employee_id = t.assigned_to
                WHERE t.ticket_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createUpdateLog(
        int $ticketId,
        string $actionType,
        ?string $oldStatus,
        ?string $newStatus,
        ?string $content,
        ?int $createdBy
    ): void {
        $sql = "INSERT INTO service_ticket_updates (
                    ticket_id,
                    action_type,
                    old_status,
                    new_status,
                    content,
                    created_by
                ) VALUES (
                    :ticket_id,
                    :action_type,
                    :old_status,
                    :new_status,
                    :content,
                    :created_by
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'ticket_id' => $ticketId,
            'action_type' => $actionType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'content' => $content,
            'created_by' => $createdBy,
        ]);
    }
}
