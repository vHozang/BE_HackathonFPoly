<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Position extends Model
{
    protected string $table = 'positions';
    protected string $primaryKey = 'position_id';
    protected array $fillable = [
        'position_code',
        'position_name',
        'position_group',
        'position_level',
        'job_description',
        'requirements',
        'salary_range_min',
        'salary_range_max',
        'status',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?string $search = null,
        ?int $status = null,
        ?string $group = null
    ): array {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(p.position_code LIKE :search OR p.position_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== null) {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        if ($group !== null && $group !== '') {
            $where[] = 'p.position_group = :position_group';
            $params['position_group'] = $group;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT p.*
                FROM positions p
                $whereSql
                ORDER BY p.position_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total FROM positions p $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }
}
