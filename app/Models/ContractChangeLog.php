<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class ContractChangeLog extends Model
{
    protected string $table = 'contract_change_logs';
    protected string $primaryKey = 'log_id';
    protected array $fillable = [
        'contract_id',
        'contract_no',
        'employee_name',
        'action_type',
        'content',
        'icon',
        'bg_class',
        'notes',
        'created_by',
    ];

    public function paginateList(int $offset, int $limit, ?string $search = null): array
    {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(l.contract_no LIKE :search OR l.employee_name LIKE :search OR l.content LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT l.*,
                       e.full_name AS actor_name,
                       e.employee_code AS actor_code
                FROM contract_change_logs l
                LEFT JOIN employees e ON e.employee_id = l.created_by
                $whereSql
                ORDER BY l.log_id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, (string) $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total FROM contract_change_logs l $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, (string) $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }

    public function findDetail(int $id): ?array
    {
        $sql = "SELECT l.*,
                       e.full_name AS actor_name,
                       e.employee_code AS actor_code
                FROM contract_change_logs l
                LEFT JOIN employees e ON e.employee_id = l.created_by
                WHERE l.log_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
