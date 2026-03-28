<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class LeaveRequest extends Model
{
    protected string $table = 'leave_requests';
    protected string $primaryKey = 'leave_request_id';
    protected array $fillable = [
        'request_id',
        'leave_type_id',
        'employee_id',
        'from_date',
        'to_date',
        'from_session',
        'to_session',
        'number_of_days',
        'leave_used_type',
        'base_days_used',
        'seniority_days_used',
        'carried_over_days_used',
        'paid_days',
        'unpaid_days',
        'substitute_employee_id',
        'handover_notes',
        'contact_phone',
        'emergency_contact',
        'doctor_note_url',
        'attachment_url',
        'insurance_claim_id',
        'certificate_file',
        'certificate_number',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?array $employeeIds = null,
        ?int $leaveTypeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $requestStatus = null
    ): array {
        $where = [];
        $params = [];

        if (is_array($employeeIds) && $employeeIds !== []) {
            $in = [];
            foreach (array_values($employeeIds) as $idx => $id) {
                $key = 'employee_id_' . $idx;
                $in[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $where[] = 'lr.employee_id IN (' . implode(', ', $in) . ')';
        }
        if ($leaveTypeId !== null) {
            $where[] = 'lr.leave_type_id = :leave_type_id';
            $params['leave_type_id'] = $leaveTypeId;
        }
        if ($dateFrom !== null && $dateFrom !== '') {
            $where[] = 'lr.from_date >= :date_from';
            $params['date_from'] = $dateFrom;
        }
        if ($dateTo !== null && $dateTo !== '') {
            $where[] = 'lr.to_date <= :date_to';
            $params['date_to'] = $dateTo;
        }
        if ($requestStatus !== null && $requestStatus !== '') {
            $where[] = 'r.status = :request_status';
            $params['request_status'] = $requestStatus;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT lr.*,
                       e.full_name AS employee_name,
                       lt.leave_type_name,
                       r.request_code,
                       r.request_type_id,
                       r.reason AS request_reason,
                       r.notes AS request_notes,
                       r.status AS request_status
                FROM leave_requests lr
                JOIN employees e ON e.employee_id = lr.employee_id
                JOIN leave_types lt ON lt.leave_type_id = lr.leave_type_id
                JOIN requests r ON r.request_id = lr.request_id
                $whereSql
                ORDER BY lr.leave_request_id DESC
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
                     FROM leave_requests lr
                     JOIN employees e ON e.employee_id = lr.employee_id
                     JOIN leave_types lt ON lt.leave_type_id = lr.leave_type_id
                     JOIN requests r ON r.request_id = lr.request_id
                     $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }
}
