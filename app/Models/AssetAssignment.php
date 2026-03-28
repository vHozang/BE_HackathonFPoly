<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class AssetAssignment extends Model
{
    protected string $table = 'asset_assignments';
    protected string $primaryKey = 'assignment_id';
    protected array $fillable = [
        'asset_id',
        'employee_id',
        'assigned_by',
        'assigned_date',
        'expected_return_date',
        'actual_return_date',
        'assignment_notes',
        'condition_before',
        'condition_after',
        'status',
    ];

    public function paginateList(int $offset, int $limit, ?int $employeeId = null, ?string $status = null): array
    {
        $where = [];
        $params = [];

        if ($employeeId !== null) {
            $where[] = 'aa.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'aa.status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT aa.*,
                       a.asset_code,
                       a.asset_name,
                       e.full_name AS employee_name,
                       ab.full_name AS assigned_by_name
                FROM asset_assignments aa
                JOIN assets a ON a.asset_id = aa.asset_id
                JOIN employees e ON e.employee_id = aa.employee_id
                JOIN employees ab ON ab.employee_id = aa.assigned_by
                $whereSql
                ORDER BY aa.assignment_id DESC
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
                     FROM asset_assignments aa
                     JOIN assets a ON a.asset_id = aa.asset_id
                     JOIN employees e ON e.employee_id = aa.employee_id
                     JOIN employees ab ON ab.employee_id = aa.assigned_by
                     $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }
}
