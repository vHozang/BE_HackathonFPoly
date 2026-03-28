<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class RecruitmentPosition extends Model
{
    protected string $table = 'recruitment_positions';
    protected string $primaryKey = 'recruitment_position_id';
    protected array $fillable = [
        'position_code',
        'position_name',
        'department_id',
        'employment_type',
        'vacancy_count',
        'description',
        'status',
        'opened_at',
        'closed_at',
        'created_by',
        'updated_by',
    ];

    public function paginateList(int $offset, int $limit, ?string $search = null, ?string $status = null): array
    {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(rp.position_code LIKE :search OR rp.position_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== null && $status !== '') {
            $where[] = 'rp.status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT rp.*,
                       d.department_name
                FROM recruitment_positions rp
                LEFT JOIN departments d ON d.department_id = rp.department_id
                $whereSql
                ORDER BY rp.recruitment_position_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total
                     FROM recruitment_positions rp
                     LEFT JOIN departments d ON d.department_id = rp.department_id
                     $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }
}
