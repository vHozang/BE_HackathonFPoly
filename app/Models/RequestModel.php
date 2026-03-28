<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class RequestModel extends Model
{
    protected string $table = 'requests';
    protected string $primaryKey = 'request_id';
    protected array $fillable = [
        'request_code',
        'request_type_id',
        'requester_id',
        'request_date',
        'from_date',
        'to_date',
        'duration',
        'reason',
        'status',
        'current_step_id',
        'is_urgent',
        'attachments',
        'notes',
        'completed_date',
        'created_by',
        'updated_by',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?string $search = null,
        ?string $status = null,
        ?array $requesterIds = null,
        ?int $requestTypeId = null
    ): array {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(r.request_code LIKE :search OR r.reason LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== null && $status !== '') {
            $where[] = 'r.status = :status';
            $params['status'] = $status;
        }
        if (is_array($requesterIds) && $requesterIds !== []) {
            $in = [];
            foreach (array_values($requesterIds) as $idx => $id) {
                $key = 'requester_id_' . $idx;
                $in[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $where[] = 'r.requester_id IN (' . implode(', ', $in) . ')';
        }
        if ($requestTypeId !== null) {
            $where[] = 'r.request_type_id = :request_type_id';
            $params['request_type_id'] = $requestTypeId;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT r.*,
                       rt.request_type_code,
                       rt.request_type_name,
                       e.full_name AS requester_name
                FROM requests r
                JOIN request_types rt ON rt.request_type_id = r.request_type_id
                JOIN employees e ON e.employee_id = r.requester_id
                $whereSql
                ORDER BY r.request_id DESC
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
                     FROM requests r
                     JOIN request_types rt ON rt.request_type_id = r.request_type_id
                     JOIN employees e ON e.employee_id = r.requester_id
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
        $sql = "SELECT r.*,
                       rt.request_type_code,
                       rt.request_type_name,
                       e.full_name AS requester_name
                FROM requests r
                JOIN request_types rt ON rt.request_type_id = r.request_type_id
                JOIN employees e ON e.employee_id = r.requester_id
                WHERE r.request_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
