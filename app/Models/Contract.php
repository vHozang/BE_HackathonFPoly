<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Contract extends Model
{
    protected string $table = 'contracts';
    protected string $primaryKey = 'contract_id';
    protected array $fillable = [
        'contract_code',
        'employee_id',
        'contract_type_id',
        'contract_number',
        'sign_date',
        'effective_date',
        'expiry_date',
        'position_id',
        'department_id',
        'basic_salary',
        'gross_salary',
        'net_salary',
        'work_location',
        'job_title',
        'status',
        'is_renewed',
        'renewed_from_contract_id',
        'termination_reason',
        'termination_date',
        'created_by',
        'updated_by',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?string $search = null,
        ?string $status = null,
        ?int $employeeId = null
    ): array {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(c.contract_code LIKE :search OR c.contract_number LIKE :search OR e.full_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($employeeId !== null) {
            $where[] = 'c.employee_id = :employee_id';
            $params['employee_id'] = $employeeId;
        }

        if ($status !== null && $status !== '') {
            $statusCode = strtoupper($status);
            if ($statusCode === 'DANG_HIEU_LUC') {
                $where[] = "c.status = 'CÓ_HIỆU_LỰC'
                            AND (c.expiry_date IS NULL OR c.expiry_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY))";
            } elseif ($statusCode === 'SAP_HET_HAN') {
                $where[] = "(c.status = 'HẾT_HẠN'
                            OR (c.status = 'CÓ_HIỆU_LỰC' AND c.expiry_date IS NOT NULL
                                AND c.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)))";
            } elseif ($statusCode === 'DA_THANH_LY') {
                $where[] = "c.status = 'ĐÃ_CHẤM_DỨT'";
            } elseif ($statusCode === 'CHO_HIEU_LUC') {
                $where[] = "c.status = 'CHỜ_HIỆU_LỰC'";
            }
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT c.*,
                       e.full_name AS employee_name,
                       e.employee_code,
                       ct.contract_type_code,
                       ct.contract_type_name,
                       p.position_name,
                       d.department_name,
                       CASE
                           WHEN c.status = 'ĐÃ_CHẤM_DỨT' THEN 'DA_THANH_LY'
                           WHEN c.status = 'CHỜ_HIỆU_LỰC' THEN 'CHO_HIEU_LUC'
                           WHEN c.status = 'HẾT_HẠN' THEN 'SAP_HET_HAN'
                           WHEN c.status = 'CÓ_HIỆU_LỰC'
                                AND c.expiry_date IS NOT NULL
                                AND c.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                THEN 'SAP_HET_HAN'
                           ELSE 'DANG_HIEU_LUC'
                       END AS ui_status
                FROM contracts c
                JOIN employees e ON e.employee_id = c.employee_id
                JOIN contract_types ct ON ct.contract_type_id = c.contract_type_id
                LEFT JOIN positions p ON p.position_id = c.position_id
                LEFT JOIN departments d ON d.department_id = c.department_id
                $whereSql
                ORDER BY c.contract_id DESC
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
                     FROM contracts c
                     JOIN employees e ON e.employee_id = c.employee_id
                     JOIN contract_types ct ON ct.contract_type_id = c.contract_type_id
                     LEFT JOIN positions p ON p.position_id = c.position_id
                     LEFT JOIN departments d ON d.department_id = c.department_id
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
        $sql = "SELECT c.*,
                       e.full_name AS employee_name,
                       e.employee_code,
                       ct.contract_type_code,
                       ct.contract_type_name,
                       p.position_name,
                       d.department_name,
                       CASE
                           WHEN c.status = 'ĐÃ_CHẤM_DỨT' THEN 'DA_THANH_LY'
                           WHEN c.status = 'CHỜ_HIỆU_LỰC' THEN 'CHO_HIEU_LUC'
                           WHEN c.status = 'HẾT_HẠN' THEN 'SAP_HET_HAN'
                           WHEN c.status = 'CÓ_HIỆU_LỰC'
                                AND c.expiry_date IS NOT NULL
                                AND c.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                                THEN 'SAP_HET_HAN'
                           ELSE 'DANG_HIEU_LUC'
                       END AS ui_status
                FROM contracts c
                JOIN employees e ON e.employee_id = c.employee_id
                JOIN contract_types ct ON ct.contract_type_id = c.contract_type_id
                LEFT JOIN positions p ON p.position_id = c.position_id
                LEFT JOIN departments d ON d.department_id = c.department_id
                WHERE c.contract_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function createHistory(
        int $contractId,
        string $action,
        int $actionBy,
        ?string $previousValue = null,
        ?string $newValue = null,
        ?string $notes = null
    ): void {
        $sql = "INSERT INTO contract_histories (
                    contract_id,
                    action,
                    action_by,
                    previous_value,
                    new_value,
                    notes
                ) VALUES (
                    :contract_id,
                    :action,
                    :action_by,
                    :previous_value,
                    :new_value,
                    :notes
                )";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'contract_id' => $contractId,
            'action' => $action,
            'action_by' => $actionBy,
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'notes' => $notes,
        ]);
    }
}
