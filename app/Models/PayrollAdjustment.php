<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class PayrollAdjustment extends Model
{
    protected string $table = 'payroll_adjustments';
    protected string $primaryKey = 'adjustment_id';
    protected array $fillable = [
        'employee_id',
        'amount',
        'description',
        'apply_month',
        'status',
        'paid_salary_detail_id',
        'paid_period_id',
        'paid_at',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?int $employeeId = null,
        ?string $applyMonth = null,
        ?int $status = null,
        ?array $employeeIds = null
    ): array {
        $where = [];
        $params = [];

        if ($employeeId !== null) {
            $where[] = 'pa.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        } elseif (is_array($employeeIds) && $employeeIds !== []) {
            $in = [];
            foreach (array_values($employeeIds) as $idx => $id) {
                $key = 'employee_scope_' . $idx;
                $in[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $where[] = 'pa.employee_id IN (' . implode(', ', $in) . ')';
        }

        if ($applyMonth !== null && $applyMonth !== '') {
            $where[] = 'pa.apply_month = :apply_month';
            $params['apply_month'] = $applyMonth;
        }

        if ($status !== null) {
            $where[] = 'pa.status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT pa.*, e.employee_code, e.full_name, sp.period_code AS paid_period_code
                FROM payroll_adjustments pa
                JOIN employees e ON e.employee_id = pa.employee_id
                LEFT JOIN salary_periods sp ON sp.period_id = pa.paid_period_id
                $whereSql
                ORDER BY pa.apply_month DESC, pa.adjustment_id DESC
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
                     FROM payroll_adjustments pa
                     JOIN employees e ON e.employee_id = pa.employee_id
                     LEFT JOIN salary_periods sp ON sp.period_id = pa.paid_period_id
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
        $sql = "SELECT pa.*, e.employee_code, e.full_name, sp.period_code AS paid_period_code
                FROM payroll_adjustments pa
                JOIN employees e ON e.employee_id = pa.employee_id
                LEFT JOIN salary_periods sp ON sp.period_id = pa.paid_period_id
                WHERE pa.adjustment_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function sumPendingByEmployeeMonth(int $employeeId, string $applyMonth): float
    {
        $sql = "SELECT COALESCE(SUM(amount), 0) AS total
                FROM payroll_adjustments
                WHERE employee_id = :employee_id
                  AND apply_month = :apply_month
                  AND status = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'employee_id' => $employeeId,
            'apply_month' => $applyMonth,
        ]);

        return (float) ($stmt->fetch()['total'] ?? 0.0);
    }

    public function listPendingByEmployeeMonth(int $employeeId, string $applyMonth): array
    {
        $sql = "SELECT *
                FROM payroll_adjustments
                WHERE employee_id = :employee_id
                  AND apply_month = :apply_month
                  AND status = 0
                ORDER BY adjustment_id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'employee_id' => $employeeId,
            'apply_month' => $applyMonth,
        ]);
        return $stmt->fetchAll() ?: [];
    }

    public function markPaidByIds(array $ids, int $salaryDetailId, int $periodId): int
    {
        if ($ids === []) {
            return 0;
        }

        $placeholders = [];
        $params = [
            'salary_detail_id' => $salaryDetailId,
            'period_id' => $periodId,
        ];
        foreach (array_values($ids) as $index => $id) {
            $key = 'id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = (int) $id;
        }

        $sql = 'UPDATE payroll_adjustments
                SET status = 1,
                    paid_salary_detail_id = :salary_detail_id,
                    paid_period_id = :period_id,
                    paid_at = NOW()
                WHERE adjustment_id IN (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function listAppliedBySalaryDetail(int $salaryDetailId): array
    {
        $sql = "SELECT pa.*
                FROM payroll_adjustments pa
                WHERE pa.paid_salary_detail_id = :salary_detail_id
                ORDER BY pa.adjustment_id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['salary_detail_id' => $salaryDetailId]);
        return $stmt->fetchAll() ?: [];
    }
}
