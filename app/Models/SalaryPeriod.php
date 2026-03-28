<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class SalaryPeriod extends Model
{
    protected string $table = 'salary_periods';
    protected string $primaryKey = 'period_id';
    protected array $fillable = [
        'period_code',
        'period_name',
        'period_type',
        'year',
        'month',
        'start_date',
        'end_date',
        'payment_date',
        'standard_working_days',
        'status',
        'closed_by',
        'closed_date',
        'notes',
    ];

    public function paginateList(int $offset, int $limit, ?int $year = null, ?string $status = null): array
    {
        $where = [];
        $params = [];

        if ($year !== null) {
            $where[] = 'sp.year = :year';
            $params['year'] = $year;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'sp.status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT sp.*, e.full_name AS closed_by_name
                FROM salary_periods sp
                LEFT JOIN employees e ON e.employee_id = sp.closed_by
                $whereSql
                ORDER BY sp.start_date DESC, sp.period_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total FROM salary_periods sp $whereSql";
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
        $sql = "SELECT sp.*, e.full_name AS closed_by_name
                FROM salary_periods sp
                LEFT JOIN employees e ON e.employee_id = sp.closed_by
                WHERE sp.period_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
