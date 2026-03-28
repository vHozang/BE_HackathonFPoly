<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class RecruitmentCandidateManagerReview extends Model
{
    protected string $table = 'recruitment_candidate_manager_reviews';
    protected string $primaryKey = 'review_id';
    protected array $fillable = [
        'candidate_id',
        'manager_id',
        'workflow_status',
        'manager_score',
        'manager_review_notes',
        'suggested_interview_date',
        'suggested_interview_time',
        'reviewed_at',
        'created_by',
        'updated_by',
    ];

    public function findByCandidateId(int $candidateId): ?array
    {
        $sql = "SELECT r.*,
                       m.full_name AS manager_name
                FROM recruitment_candidate_manager_reviews r
                LEFT JOIN employees m ON m.employee_id = r.manager_id
                WHERE r.candidate_id = :candidate_id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['candidate_id' => $candidateId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function upsertByCandidateId(int $candidateId, array $payload): int
    {
        $data = $this->filterFillable($payload);
        $data['candidate_id'] = $candidateId;

        if (!isset($data['workflow_status'])) {
            $data['workflow_status'] = 'PENDING';
        }

        $sql = "INSERT INTO recruitment_candidate_manager_reviews (
                    candidate_id,
                    manager_id,
                    workflow_status,
                    manager_score,
                    manager_review_notes,
                    suggested_interview_date,
                    suggested_interview_time,
                    reviewed_at,
                    created_by,
                    updated_by
                ) VALUES (
                    :candidate_id,
                    :manager_id,
                    :workflow_status,
                    :manager_score,
                    :manager_review_notes,
                    :suggested_interview_date,
                    :suggested_interview_time,
                    :reviewed_at,
                    :created_by,
                    :updated_by
                )
                ON DUPLICATE KEY UPDATE
                    manager_id = VALUES(manager_id),
                    workflow_status = VALUES(workflow_status),
                    manager_score = VALUES(manager_score),
                    manager_review_notes = VALUES(manager_review_notes),
                    suggested_interview_date = VALUES(suggested_interview_date),
                    suggested_interview_time = VALUES(suggested_interview_time),
                    reviewed_at = VALUES(reviewed_at),
                    updated_by = VALUES(updated_by)";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'candidate_id' => $candidateId,
            'manager_id' => $data['manager_id'] ?? null,
            'workflow_status' => $data['workflow_status'] ?? 'PENDING',
            'manager_score' => $data['manager_score'] ?? null,
            'manager_review_notes' => $data['manager_review_notes'] ?? null,
            'suggested_interview_date' => $data['suggested_interview_date'] ?? null,
            'suggested_interview_time' => $data['suggested_interview_time'] ?? null,
            'reviewed_at' => $data['reviewed_at'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'updated_by' => $data['updated_by'] ?? null,
        ]);

        $saved = $this->findByCandidateId($candidateId);
        return (int) ($saved['review_id'] ?? 0);
    }
}
