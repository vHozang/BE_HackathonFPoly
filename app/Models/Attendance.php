<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Attendance extends Model
{
    protected string $table = 'attendances';
    protected string $primaryKey = 'attendance_id';
    protected array $fillable = [
        'employee_id',
        'attendance_date',
        'shift_type_id',
        'check_in_time',
        'check_out_time',
        'check_in_method',
        'check_out_method',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_latitude',
        'check_out_longitude',
        'work_type',
        'actual_working_hours',
        'overtime_hours',
        'late_minutes',
        'early_leave_minutes',
        'is_holiday',
        'is_overtime',
        'status',
        'notes',
        'approved_by',
        'approved_date',
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
            $where[] = 'a.attendance_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $where[] = 'a.attendance_date <= :date_to';
            $params['date_to'] = $dateTo;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'a.status = :status';
            $params['status'] = $status;
        }
        if (is_array($employeeIds) && $employeeIds !== []) {
            $in = [];
            foreach (array_values($employeeIds) as $idx => $id) {
                $key = 'employee_id_' . $idx;
                $in[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $where[] = 'a.employee_id IN (' . implode(', ', $in) . ')';
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT a.*, e.employee_code, e.full_name, st.shift_code, st.shift_name
                FROM attendances a
                JOIN employees e ON e.employee_id = a.employee_id
                LEFT JOIN shift_types st ON st.shift_type_id = a.shift_type_id
                $whereSql
                ORDER BY a.attendance_date DESC, a.attendance_id DESC
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
                     FROM attendances a
                     JOIN employees e ON e.employee_id = a.employee_id
                     LEFT JOIN shift_types st ON st.shift_type_id = a.shift_type_id
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
        $sql = "SELECT a.*, e.employee_code, e.full_name, st.shift_code, st.shift_name
                FROM attendances a
                JOIN employees e ON e.employee_id = a.employee_id
                LEFT JOIN shift_types st ON st.shift_type_id = a.shift_type_id
                WHERE a.attendance_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
