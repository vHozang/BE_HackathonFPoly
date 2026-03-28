<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class OvertimeRequest extends Model
{
    protected string $table = 'overtime_requests';
    protected string $primaryKey = 'overtime_id';
    protected array $fillable = [
        'request_id',
        'employee_id',
        'overtime_date',
        'start_time',
        'end_time',
        'break_time',
        'reason',
        'approved_by',
        'approved_date',
        'status',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?array $employeeIds = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $status = null
    ): array {
        $where = [];
        $params = [];

        if ($dateFrom !== null && $dateFrom !== '') {
            $where[] = 'ot.overtime_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $where[] = 'ot.overtime_date <= :date_to';
            $params['date_to'] = $dateTo;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'ot.status = :status';
            $params['status'] = $status;
        }
        if (is_array($employeeIds) && $employeeIds !== []) {
            $in = [];
            foreach (array_values($employeeIds) as $idx => $id) {
                $key = 'employee_id_' . $idx;
                $in[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $where[] = 'ot.employee_id IN (' . implode(', ', $in) . ')';
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT ot.*, e.employee_code, e.full_name, r.request_code
                FROM overtime_requests ot
                JOIN employees e ON e.employee_id = ot.employee_id
                JOIN requests r ON r.request_id = ot.request_id
                $whereSql
                ORDER BY ot.overtime_date DESC, ot.overtime_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue(':' . $key, (string) $value, PDO::PARAM_STR);
            }
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total
                     FROM overtime_requests ot
                     JOIN employees e ON e.employee_id = ot.employee_id
                     JOIN requests r ON r.request_id = ot.request_id
                     $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $countStmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
            } else {
                $countStmt->bindValue(':' . $key, (string) $value, PDO::PARAM_STR);
            }
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }

    public function findDetail(int $id): ?array
    {
        $sql = "SELECT ot.*, e.employee_code, e.full_name, r.request_code
                FROM overtime_requests ot
                JOIN employees e ON e.employee_id = ot.employee_id
                JOIN requests r ON r.request_id = ot.request_id
                WHERE ot.overtime_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
