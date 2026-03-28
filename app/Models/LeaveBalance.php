<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class LeaveBalance extends Model
{
    protected string $table = 'leave_balances';
    protected string $primaryKey = 'balance_id';
    protected array $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'base_leave',
        'seniority_bonus',
        'total_days',
        'carried_over_days',
        'carried_over_source',
        'used_days',
        'pending_days',
        'remaining_days',
        'carry_over_expiry_date',
        'notes',
    ];

    public function paginateList(int $offset, int $limit, ?array $employeeIds = null, ?int $year = null): array
    {
        $where = [];
        $params = [];

        if (is_array($employeeIds) && $employeeIds !== []) {
            $in = [];
            foreach (array_values($employeeIds) as $idx => $id) {
                $key = 'employee_id_' . $idx;
                $in[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $where[] = 'lb.employee_id IN (' . implode(', ', $in) . ')';
        }
        if ($year !== null) {
            $where[] = 'lb.year = :year';
            $params['year'] = $year;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT lb.*,
                       e.full_name AS employee_name,
                       e.employee_code,
                       lt.leave_type_name
                FROM leave_balances lb
                JOIN employees e ON e.employee_id = lb.employee_id
                JOIN leave_types lt ON lt.leave_type_id = lb.leave_type_id
                $whereSql
                ORDER BY lb.balance_id DESC
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
                     FROM leave_balances lb
                     JOIN employees e ON e.employee_id = lb.employee_id
                     JOIN leave_types lt ON lt.leave_type_id = lb.leave_type_id
                     $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }
}
