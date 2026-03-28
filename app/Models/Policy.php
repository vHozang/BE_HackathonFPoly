<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Policy extends Model
{
    protected string $table = 'policies';
    protected string $primaryKey = 'policy_id';
    protected array $fillable = [
        'policy_code',
        'policy_name',
        'policy_type',
        'content',
        'version',
        'effective_date',
        'expiry_date',
        'department_id',
        'file_url',
        'is_required_acknowledgment',
        'status',
        'created_by',
        'approved_by',
        'approved_date',
    ];

    public function paginateList(int $offset, int $limit, ?string $search = null, ?string $status = null): array
    {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(p.policy_code LIKE :search OR p.policy_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== null && $status !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT p.*, d.department_name, cb.full_name AS created_by_name, ab.full_name AS approved_by_name
                FROM policies p
                LEFT JOIN departments d ON d.department_id = p.department_id
                LEFT JOIN employees cb ON cb.employee_id = p.created_by
                LEFT JOIN employees ab ON ab.employee_id = p.approved_by
                $whereSql
                ORDER BY p.policy_id DESC
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
                     FROM policies p
                     LEFT JOIN departments d ON d.department_id = p.department_id
                     LEFT JOIN employees cb ON cb.employee_id = p.created_by
                     LEFT JOIN employees ab ON ab.employee_id = p.approved_by
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
