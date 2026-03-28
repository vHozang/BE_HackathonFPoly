<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class SalaryDetail extends Model
{
    protected string $table = 'salary_details';
    protected string $primaryKey = 'salary_detail_id';
    protected array $fillable = [
        'period_id',
        'employee_id',
        'contract_id',
        'basic_salary',
        'gross_salary',
        'net_salary',
        'total_allowances',
        'total_deductions',
        'overtime_pay',
        'leave_pay',
        'bonus',
        'penalty',
        'social_insurance_employee',
        'social_insurance_company',
        'health_insurance_employee',
        'health_insurance_company',
        'unemployment_insurance_employee',
        'unemployment_insurance_company',
        'personal_income_tax',
        'advance_payment',
        'bank_account',
        'bank_name',
        'transfer_status',
        'transfer_date',
        'notes',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?int $periodId = null,
        ?array $employeeIds = null,
        ?string $transferStatus = null
    ): array {
        $where = [];
        $params = [];

        if ($periodId !== null) {
            $where[] = 'sd.period_id = :period_id';
            $params['period_id'] = $periodId;
        }
        if ($transferStatus !== null && $transferStatus !== '') {
            $where[] = 'sd.transfer_status = :transfer_status';
            $params['transfer_status'] = $transferStatus;
        }
        if (is_array($employeeIds) && $employeeIds !== []) {
            $in = [];
            foreach (array_values($employeeIds) as $idx => $id) {
                $key = 'employee_id_' . $idx;
                $in[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $where[] = 'sd.employee_id IN (' . implode(', ', $in) . ')';
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT sd.*,
                       e.employee_code,
                       e.full_name,
                       sp.period_code,
                       sp.period_name,
                       sp.status AS period_status,
                       DATE_FORMAT(sp.start_date, '%Y-%m') AS period_month_key,
                       (
                           SELECT COALESCE(SUM(pa.amount), 0)
                           FROM payroll_adjustments pa
                           WHERE pa.employee_id = sd.employee_id
                             AND pa.apply_month = DATE_FORMAT(sp.start_date, '%Y-%m')
                             AND pa.status = 0
                       ) AS pending_adjustment_total,
                       (
                           SELECT COALESCE(SUM(pa2.amount), 0)
                           FROM payroll_adjustments pa2
                           WHERE pa2.paid_salary_detail_id = sd.salary_detail_id
                             AND pa2.status = 1
                       ) AS applied_adjustment_total
                FROM salary_details sd
                JOIN employees e ON e.employee_id = sd.employee_id
                JOIN salary_periods sp ON sp.period_id = sd.period_id
                $whereSql
                ORDER BY sd.salary_detail_id DESC
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
                     FROM salary_details sd
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
        $sql = "SELECT sd.*,
                       e.employee_code,
                       e.full_name,
                       sp.period_code,
                       sp.period_name,
                       sp.status AS period_status,
                       DATE_FORMAT(sp.start_date, '%Y-%m') AS period_month_key,
                       (
                           SELECT COALESCE(SUM(pa.amount), 0)
                           FROM payroll_adjustments pa
                           WHERE pa.employee_id = sd.employee_id
                             AND pa.apply_month = DATE_FORMAT(sp.start_date, '%Y-%m')
                             AND pa.status = 0
                       ) AS pending_adjustment_total,
                       (
                           SELECT COALESCE(SUM(pa2.amount), 0)
                           FROM payroll_adjustments pa2
                           WHERE pa2.paid_salary_detail_id = sd.salary_detail_id
                             AND pa2.status = 1
                       ) AS applied_adjustment_total
                FROM salary_details sd
                JOIN employees e ON e.employee_id = sd.employee_id
                JOIN salary_periods sp ON sp.period_id = sd.period_id
                WHERE sd.salary_detail_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function listByPeriodId(int $periodId): array
    {
        $sql = "SELECT sd.*, sp.start_date, sp.period_code, sp.period_name, sp.status AS period_status
                FROM salary_details sd
                JOIN salary_periods sp ON sp.period_id = sd.period_id
                WHERE sd.period_id = :period_id
                ORDER BY sd.salary_detail_id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['period_id' => $periodId]);
        return $stmt->fetchAll() ?: [];
    }
}
