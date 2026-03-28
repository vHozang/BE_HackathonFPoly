<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class InterviewSchedule extends Model
{
    protected string $table = 'interview_schedules';
    protected string $primaryKey = 'interview_id';
    protected array $fillable = [
        'candidate_id',
        'interviewer_id',
        'department_manager_id',
        'interview_date',
        'interview_time',
        'interview_mode',
        'meeting_link',
        'location',
        'status',
        'result',
        'evaluation_notes',
        'manager_review_notes',
        'manager_decision',
        'reviewed_at',
        'created_by',
        'updated_by',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?string $status = null,
        ?int $interviewerId = null,
        ?int $candidateId = null
    ): array {
        $where = [];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        if ($interviewerId !== null) {
            $where[] = 'i.interviewer_id = :interviewer_id';
            $params['interviewer_id'] = $interviewerId;
        }
        if ($candidateId !== null) {
            $where[] = 'i.candidate_id = :candidate_id';
            $params['candidate_id'] = $candidateId;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT i.*,
                       c.full_name AS candidate_name,
                       rp.position_name,
                       rp.department_id,
                       d.department_name,
                       e.full_name AS interviewer_name,
                       dm.full_name AS department_manager_name
                FROM interview_schedules i
                JOIN recruitment_candidates c ON c.candidate_id = i.candidate_id
                LEFT JOIN recruitment_positions rp ON rp.recruitment_position_id = c.recruitment_position_id
                LEFT JOIN employees e ON e.employee_id = i.interviewer_id
                LEFT JOIN departments d ON d.department_id = rp.department_id
                LEFT JOIN employees dm ON dm.employee_id = i.department_manager_id
                $whereSql
                ORDER BY i.interview_date DESC, i.interview_time DESC, i.interview_id DESC
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
                     FROM interview_schedules i
                     JOIN recruitment_candidates c ON c.candidate_id = i.candidate_id
                     LEFT JOIN recruitment_positions rp ON rp.recruitment_position_id = c.recruitment_position_id
                     LEFT JOIN employees e ON e.employee_id = i.interviewer_id
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
        $sql = "SELECT i.*,
                       c.full_name AS candidate_name,
                       rp.position_name,
                       rp.department_id,
                       d.department_name,
                       e.full_name AS interviewer_name,
                       dm.full_name AS department_manager_name
                FROM interview_schedules i
                JOIN recruitment_candidates c ON c.candidate_id = i.candidate_id
                LEFT JOIN recruitment_positions rp ON rp.recruitment_position_id = c.recruitment_position_id
                LEFT JOIN employees e ON e.employee_id = i.interviewer_id
                LEFT JOIN departments d ON d.department_id = rp.department_id
                LEFT JOIN employees dm ON dm.employee_id = i.department_manager_id
                WHERE i.interview_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findReviewContext(int $id): ?array
    {
        $sql = "SELECT i.interview_id,
                       i.candidate_id,
                       i.department_manager_id,
                       i.status,
                       i.result,
                       c.full_name AS candidate_name,
                       c.recruitment_position_id,
                       rp.position_name,
                       rp.department_id,
                       d.department_name,
                       d.manager_id AS current_department_manager_id
                FROM interview_schedules i
                JOIN recruitment_candidates c ON c.candidate_id = i.candidate_id
                LEFT JOIN recruitment_positions rp ON rp.recruitment_position_id = c.recruitment_position_id
                LEFT JOIN departments d ON d.department_id = rp.department_id
                WHERE i.interview_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findLatestByCandidate(int $candidateId): ?array
    {
        $sql = "SELECT i.*
                FROM interview_schedules i
                WHERE i.candidate_id = :candidate_id
                ORDER BY i.interview_id DESC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['candidate_id' => $candidateId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
