<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class RecruitmentCandidate extends Model
{
    protected string $table = 'recruitment_candidates';
    protected string $primaryKey = 'candidate_id';
    protected array $fillable = [
        'candidate_code',
        'full_name',
        'email',
        'phone_number',
        'recruitment_position_id',
        'cv_url',
        'source_channel',
        'ai_score',
        'application_status',
        'applied_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function paginateList(
        int $offset,
        int $limit,
        ?string $search = null,
        ?string $status = null,
        ?int $positionId = null
    ): array {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(c.full_name LIKE :search OR c.email LIKE :search OR rp.position_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        if ($status !== null && $status !== '') {
            $where[] = 'c.application_status = :application_status';
            $params['application_status'] = $status;
        }

        if ($positionId !== null) {
            $where[] = 'c.recruitment_position_id = :recruitment_position_id';
            $params['recruitment_position_id'] = $positionId;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT c.*,
                       rp.position_name,
                       rm.review_id,
                       rm.manager_id,
                       rm.workflow_status,
                       rm.manager_score,
                       rm.manager_review_notes,
                       rm.suggested_interview_date,
                       rm.suggested_interview_time,
                       rm.reviewed_at,
                       cv.cv_id,
                       cv.original_filename AS cv_original_filename,
                       cv.mime_type AS cv_mime_type,
                       cv.file_size AS cv_file_size,
                       CASE WHEN cv.cv_id IS NULL THEN 0 ELSE 1 END AS has_cv,
                       li.interview_id AS latest_interview_id,
                       li.interview_date AS latest_interview_date,
                       li.interview_time AS latest_interview_time,
                       li.status AS latest_interview_status
                FROM recruitment_candidates c
                LEFT JOIN recruitment_positions rp ON rp.recruitment_position_id = c.recruitment_position_id
                LEFT JOIN recruitment_candidate_manager_reviews rm ON rm.candidate_id = c.candidate_id
                LEFT JOIN recruitment_candidate_cvs cv ON cv.candidate_id = c.candidate_id
                LEFT JOIN (
                    SELECT i1.candidate_id,
                           i1.interview_id,
                           i1.interview_date,
                           i1.interview_time,
                           i1.status
                    FROM interview_schedules i1
                    JOIN (
                        SELECT candidate_id, MAX(interview_id) AS latest_id
                        FROM interview_schedules
                        GROUP BY candidate_id
                    ) i2 ON i2.latest_id = i1.interview_id
                ) li ON li.candidate_id = c.candidate_id
                $whereSql
                ORDER BY c.candidate_id DESC
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
                     FROM recruitment_candidates c
                     LEFT JOIN recruitment_positions rp ON rp.recruitment_position_id = c.recruitment_position_id
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
                       rp.position_name,
                       rp.department_id,
                       d.department_name,
                       d.manager_id AS department_manager_id,
                       rm.review_id,
                       rm.manager_id,
                       rm.workflow_status,
                       rm.manager_score,
                       rm.manager_review_notes,
                       rm.suggested_interview_date,
                       rm.suggested_interview_time,
                       rm.reviewed_at,
                       cv.cv_id,
                       cv.original_filename AS cv_original_filename,
                       cv.mime_type AS cv_mime_type,
                       cv.file_size AS cv_file_size,
                       CASE WHEN cv.cv_id IS NULL THEN 0 ELSE 1 END AS has_cv
                FROM recruitment_candidates c
                LEFT JOIN recruitment_positions rp ON rp.recruitment_position_id = c.recruitment_position_id
                LEFT JOIN departments d ON d.department_id = rp.department_id
                LEFT JOIN recruitment_candidate_manager_reviews rm ON rm.candidate_id = c.candidate_id
                LEFT JOIN recruitment_candidate_cvs cv ON cv.candidate_id = c.candidate_id
                WHERE c.candidate_id = :id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findInterviewRouting(int $candidateId): ?array
    {
        $sql = "SELECT c.candidate_id,
                       c.full_name AS candidate_name,
                       c.application_status,
                       c.recruitment_position_id,
                       rp.position_name,
                       rp.department_id,
                       d.department_name,
                       d.manager_id AS department_manager_id,
                       m.full_name AS department_manager_name
                FROM recruitment_candidates c
                JOIN recruitment_positions rp ON rp.recruitment_position_id = c.recruitment_position_id
                LEFT JOIN departments d ON d.department_id = rp.department_id
                LEFT JOIN employees m ON m.employee_id = d.manager_id
                WHERE c.candidate_id = :candidate_id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['candidate_id' => $candidateId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
