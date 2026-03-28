<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class SalaryBreakdown extends Model
{
    protected string $table = 'salary_breakdowns';
    protected string $primaryKey = 'breakdown_id';
    protected array $fillable = [
        'salary_detail_id',
        'item_type',
        'item_id',
        'item_name',
        'amount',
        'is_taxable',
        'is_insurable',
        'description',
    ];

    public function paginateList(int $offset, int $limit, ?int $salaryDetailId = null, ?string $itemType = null): array
    {
        $where = [];
        $params = [];

        if ($salaryDetailId !== null) {
            $where[] = 'sb.salary_detail_id = :salary_detail_id';
            $params['salary_detail_id'] = $salaryDetailId;
        }
        if ($itemType !== null && $itemType !== '') {
            $where[] = 'sb.item_type = :item_type';
            $params['item_type'] = $itemType;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT sb.*, sd.employee_id, e.employee_code, e.full_name, sp.period_code
                FROM salary_breakdowns sb
                JOIN salary_details sd ON sd.salary_detail_id = sb.salary_detail_id
                JOIN employees e ON e.employee_id = sd.employee_id
                JOIN salary_periods sp ON sp.period_id = sd.period_id
                $whereSql
                ORDER BY sb.breakdown_id DESC
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
                     FROM salary_breakdowns sb
                     JOIN salary_details sd ON sd.salary_detail_id = sb.salary_detail_id
                     JOIN employees e ON e.employee_id = sd.employee_id
                     JOIN salary_periods sp ON sp.period_id = sd.period_id
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
        $sql = "SELECT sb.*, sd.employee_id, e.employee_code, e.full_name, sp.period_code
                FROM salary_breakdowns sb
                JOIN salary_details sd ON sd.salary_detail_id = sb.salary_detail_id
                JOIN employees e ON e.employee_id = sd.employee_id
                JOIN salary_periods sp ON sp.period_id = sd.period_id
                WHERE sb.breakdown_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
