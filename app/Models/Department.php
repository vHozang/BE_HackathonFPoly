<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Department extends Model
{
    protected string $table = 'departments';
    protected string $primaryKey = 'department_id';
    protected array $fillable = [
        'department_code',
        'department_name',
        'parent_department_id',
        'manager_id',
        'description',
        'status',
    ];

    public function paginateList(int $offset, int $limit, ?string $search = null, ?int $managerId = null): array
    {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(d.department_code LIKE :search OR d.department_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($managerId !== null) {
            $where[] = 'd.manager_id = :manager_id';
            $params['manager_id'] = $managerId;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT d.*,
                       m.full_name AS manager_name,
                       p.department_name AS parent_department_name
                FROM departments d
                LEFT JOIN employees m ON m.employee_id = d.manager_id
                LEFT JOIN departments p ON p.department_id = d.parent_department_id
                $whereSql
                ORDER BY d.department_id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total FROM departments d $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }
}
