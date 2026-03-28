<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class RecruitmentCandidateCv extends Model
{
    protected string $table = 'recruitment_candidate_cvs';
    protected string $primaryKey = 'cv_id';
    protected array $fillable = [
        'candidate_id',
        'original_filename',
        'mime_type',
        'file_size',
        'file_data',
        'uploaded_by',
    ];

    public function upsertByCandidate(int $candidateId, array $payload): void
    {
        $sql = "INSERT INTO recruitment_candidate_cvs (
                    candidate_id,
                    original_filename,
                    mime_type,
                    file_size,
                    file_data,
                    uploaded_by
                ) VALUES (
                    :candidate_id,
                    :original_filename,
                    :mime_type,
                    :file_size,
                    :file_data,
                    :uploaded_by
                )
                ON DUPLICATE KEY UPDATE
                    original_filename = VALUES(original_filename),
                    mime_type = VALUES(mime_type),
                    file_size = VALUES(file_size),
                    file_data = VALUES(file_data),
                    uploaded_by = VALUES(uploaded_by),
                    updated_at = CURRENT_TIMESTAMP";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':candidate_id', $candidateId, \PDO::PARAM_INT);
        $stmt->bindValue(':original_filename', (string) ($payload['original_filename'] ?? 'cv.pdf'), \PDO::PARAM_STR);
        $stmt->bindValue(':mime_type', (string) ($payload['mime_type'] ?? 'application/pdf'), \PDO::PARAM_STR);
        $stmt->bindValue(':file_size', (int) ($payload['file_size'] ?? 0), \PDO::PARAM_INT);
        $stmt->bindValue(':file_data', (string) ($payload['file_data'] ?? ''), \PDO::PARAM_LOB);
        if (isset($payload['uploaded_by']) && $payload['uploaded_by'] !== null) {
            $stmt->bindValue(':uploaded_by', (int) $payload['uploaded_by'], \PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':uploaded_by', null, \PDO::PARAM_NULL);
        }
        $stmt->execute();
    }

    public function findMetaByCandidate(int $candidateId): ?array
    {
        $sql = "SELECT cv_id,
                       candidate_id,
                       original_filename,
                       mime_type,
                       file_size,
                       uploaded_by,
                       created_at,
                       updated_at
                FROM recruitment_candidate_cvs
                WHERE candidate_id = :candidate_id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['candidate_id' => $candidateId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function findFileByCandidate(int $candidateId): ?array
    {
        $sql = "SELECT cv_id,
                       candidate_id,
                       original_filename,
                       mime_type,
                       file_size,
                       file_data
                FROM recruitment_candidate_cvs
                WHERE candidate_id = :candidate_id
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['candidate_id' => $candidateId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }
}
