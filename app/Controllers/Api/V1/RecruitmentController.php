<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\InterviewSchedule;
use App\Models\Notification;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentCandidateCv;
use App\Models\RecruitmentCandidateManagerReview;
use App\Models\RecruitmentPosition;

class RecruitmentController extends Controller
{
    private const MAX_CV_FILE_SIZE = 10485760; // 10 MB

    private RecruitmentPosition $positions;
    private RecruitmentCandidate $candidates;
    private RecruitmentCandidateCv $candidateCvs;
    private RecruitmentCandidateManagerReview $managerReviews;
    private InterviewSchedule $interviews;
    private Notification $notifications;

    public function __construct()
    {
        $this->positions = new RecruitmentPosition();
        $this->candidates = new RecruitmentCandidate();
        $this->candidateCvs = new RecruitmentCandidateCv();
        $this->managerReviews = new RecruitmentCandidateManagerReview();
        $this->interviews = new InterviewSchedule();
        $this->notifications = new Notification();
    }

    public function positionIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');

        $result = $this->positions->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($status) ? $status : null
        );

        return $this->ok(
            $result['items'],
            'Recruitment position list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function positionStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'position_code' => ['string'],
            'position_name' => ['required', 'string'],
            'department_id' => ['integer'],
            'employment_type' => ['string'],
            'vacancy_count' => ['integer'],
            'description' => ['string'],
            'status' => ['string'],
            'opened_at' => ['date'],
            'closed_at' => ['date'],
        ]);

        if (!isset($payload['position_code']) || trim((string) $payload['position_code']) === '') {
            $payload['position_code'] = 'REC-' . date('Y') . '-' . random_int(100, 999);
        }
        $payload['employment_type'] = $this->normalizeEmploymentType($payload['employment_type'] ?? null);
        $payload['status'] = $this->normalizeRecruitmentPositionStatus($payload['status'] ?? null);
        $payload['vacancy_count'] = (int) ($payload['vacancy_count'] ?? 1);

        $actorId = (int) (($request->attribute('auth_user')['employee_id'] ?? 0));
        $payload['created_by'] = $actorId > 0 ? $actorId : null;
        $payload['updated_by'] = $actorId > 0 ? $actorId : null;

        $id = $this->positions->create($payload);
        return $this->created($this->positions->find($id), 'Recruitment position created');
    }

    public function positionUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->positions->find($id);
        if ($existing === null) {
            throw new HttpException('Recruitment position not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'position_name' => ['string'],
            'department_id' => ['integer'],
            'employment_type' => ['string'],
            'vacancy_count' => ['integer'],
            'description' => ['string'],
            'status' => ['string'],
            'opened_at' => ['date'],
            'closed_at' => ['date'],
        ]);

        if (isset($payload['employment_type'])) {
            $payload['employment_type'] = $this->normalizeEmploymentType($payload['employment_type']);
        }
        if (isset($payload['status'])) {
            $payload['status'] = $this->normalizeRecruitmentPositionStatus($payload['status']);
        }

        $actorId = (int) (($request->attribute('auth_user')['employee_id'] ?? 0));
        $payload['updated_by'] = $actorId > 0 ? $actorId : null;

        $this->positions->updateById($id, $payload);
        return $this->ok($this->positions->find($id), 'Recruitment position updated');
    }

    public function positionDelete(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->positions->find($id);
        if ($existing === null) {
            throw new HttpException('Recruitment position not found', 404, 'not_found');
        }
        $this->positions->deleteById($id);
        return $this->ok(null, 'Recruitment position deleted');
    }

    public function candidateIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');
        $positionId = $request->query('recruitment_position_id') !== null ? (int) $request->query('recruitment_position_id') : null;

        $result = $this->candidates->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($status) ? $status : null,
            $positionId
        );

        return $this->ok(
            $this->mapCandidateListWithCvAccess($result['items']),
            'Recruitment candidate list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function candidateShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->candidates->findDetail($id);
        if ($item === null) {
            throw new HttpException('Candidate not found', 404, 'not_found');
        }
        return $this->ok($this->mapCandidateWithCvAccess($item), 'Candidate detail');
    }

    public function candidateStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'candidate_code' => ['string'],
            'full_name' => ['required', 'string'],
            'email' => ['string'],
            'phone_number' => ['string'],
            'recruitment_position_id' => ['required', 'integer'],
            'cv_url' => ['string'],
            'source_channel' => ['string'],
            'ai_score' => ['numeric'],
            'application_status' => ['string'],
            'applied_at' => ['date'],
            'notes' => ['string'],
        ]);

        if (!isset($payload['candidate_code']) || trim((string) $payload['candidate_code']) === '') {
            $payload['candidate_code'] = 'UV-' . date('Y') . '-' . random_int(1000, 9999);
        }
        if (!isset($payload['application_status'])) {
            $payload['application_status'] = 'NEW';
        }
        $payload['application_status'] = $this->normalizeCandidateStatus((string) $payload['application_status']);
        if (!isset($payload['applied_at'])) {
            $payload['applied_at'] = date('Y-m-d');
        }

        $actorId = (int) (($request->attribute('auth_user')['employee_id'] ?? 0));
        $payload['created_by'] = $actorId > 0 ? $actorId : null;
        $payload['updated_by'] = $actorId > 0 ? $actorId : null;

        $id = $this->candidates->create($payload);

        $this->managerReviews->upsertByCandidateId($id, [
            'workflow_status' => 'PENDING',
            'created_by' => $actorId > 0 ? $actorId : null,
            'updated_by' => $actorId > 0 ? $actorId : null,
        ]);

        $saved = $this->candidates->findDetail($id);
        return $this->created($saved !== null ? $this->mapCandidateWithCvAccess($saved) : null, 'Candidate created');
    }

    public function candidateUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->candidates->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Candidate not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'full_name' => ['string'],
            'email' => ['string'],
            'phone_number' => ['string'],
            'recruitment_position_id' => ['integer'],
            'cv_url' => ['string'],
            'source_channel' => ['string'],
            'ai_score' => ['numeric'],
            'application_status' => ['string'],
            'applied_at' => ['date'],
            'notes' => ['string'],
        ]);

        if (isset($payload['application_status'])) {
            $payload['application_status'] = $this->normalizeCandidateStatus((string) $payload['application_status']);
        }

        $actorId = (int) (($request->attribute('auth_user')['employee_id'] ?? 0));
        $payload['updated_by'] = $actorId > 0 ? $actorId : null;

        $this->candidates->updateById($id, $payload);
        $saved = $this->candidates->findDetail($id);
        return $this->ok($saved !== null ? $this->mapCandidateWithCvAccess($saved) : null, 'Candidate updated');
    }

    public function candidateManagerReviewShow(Request $request, array $params): array
    {
        $candidateId = (int) ($params['id'] ?? 0);
        $candidate = $this->candidates->findDetail($candidateId);
        if ($candidate === null) {
            throw new HttpException('Candidate not found', 404, 'not_found');
        }

        $review = $this->managerReviews->findByCandidateId($candidateId);
        if ($review === null) {
            $review = [
                'candidate_id' => $candidateId,
                'workflow_status' => 'PENDING',
                'manager_id' => null,
                'manager_score' => null,
                'manager_review_notes' => null,
                'suggested_interview_date' => null,
                'suggested_interview_time' => null,
                'reviewed_at' => null,
            ];
        }

        return $this->ok($review, 'Candidate manager review');
    }

    public function candidateManagerReviewUpsert(Request $request, array $params): array
    {
        $candidateId = (int) ($params['id'] ?? 0);
        $candidate = $this->candidates->findDetail($candidateId);
        if ($candidate === null) {
            throw new HttpException('Candidate not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'workflow_status' => ['string'],
            'manager_decision' => ['string'],
            'manager_id' => ['integer'],
            'manager_score' => ['numeric'],
            'manager_review_notes' => ['string'],
            'suggested_interview_date' => ['date'],
            'suggested_interview_time' => ['string'],
        ]);

        $actorId = (int) (($request->attribute('auth_user')['employee_id'] ?? 0));
        $decisionInput = (string) ($payload['manager_decision'] ?? $payload['workflow_status'] ?? 'PENDING');
        $workflowStatus = $this->normalizeManagerWorkflowStatus($decisionInput);

        $managerId = isset($payload['manager_id']) ? (int) $payload['manager_id'] : null;
        if (($managerId === null || $managerId <= 0) && $workflowStatus !== 'PENDING' && $actorId > 0) {
            $managerId = $actorId;
        }

        $reviewPayload = [
            'manager_id' => ($managerId !== null && $managerId > 0) ? $managerId : null,
            'workflow_status' => $workflowStatus,
            'manager_score' => $payload['manager_score'] ?? null,
            'manager_review_notes' => $payload['manager_review_notes'] ?? null,
            'suggested_interview_date' => $payload['suggested_interview_date'] ?? null,
            'suggested_interview_time' => $payload['suggested_interview_time'] ?? null,
            'reviewed_at' => in_array($workflowStatus, ['APPROVED', 'REJECTED'], true) ? date('Y-m-d H:i:s') : null,
            'created_by' => $actorId > 0 ? $actorId : null,
            'updated_by' => $actorId > 0 ? $actorId : null,
        ];

        $this->managerReviews->upsertByCandidateId($candidateId, $reviewPayload);
        $this->syncCandidateStatusAfterManagerWorkflow(
            $candidateId,
            (string) ($candidate['application_status'] ?? 'NEW'),
            $workflowStatus,
            $actorId > 0 ? $actorId : null
        );
        $this->syncInterviewSuggestionFromManagerWorkflow($candidateId, $reviewPayload, $actorId > 0 ? $actorId : null);

        $savedReview = $this->managerReviews->findByCandidateId($candidateId);
        return $this->ok($savedReview, 'Candidate manager review updated');
    }

    public function candidateUploadCv(Request $request, array $params): array
    {
        $candidateId = (int) ($params['id'] ?? 0);
        $candidate = $this->candidates->findDetail($candidateId);
        if ($candidate === null) {
            throw new HttpException('Candidate not found', 404, 'not_found');
        }

        if (!isset($_FILES['file']) || !is_array($_FILES['file'])) {
            throw new HttpException('CV file is required (field: file).', 422, 'validation_error');
        }

        $upload = $_FILES['file'];
        $errorCode = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new HttpException('Uploaded CV is invalid.', 422, 'validation_error');
        }

        $tmpName = (string) ($upload['tmp_name'] ?? '');
        $originalName = trim((string) ($upload['name'] ?? ''));
        $fileSize = (int) ($upload['size'] ?? 0);

        if ($tmpName === '' || !is_file($tmpName)) {
            throw new HttpException('Uploaded CV is invalid.', 422, 'validation_error');
        }
        if ($fileSize <= 0) {
            throw new HttpException('Uploaded CV is empty.', 422, 'validation_error');
        }
        if ($fileSize > self::MAX_CV_FILE_SIZE) {
            throw new HttpException('CV size must be <= 10MB.', 422, 'validation_error');
        }

        $fileContent = file_get_contents($tmpName);
        if ($fileContent === false || $fileContent === '') {
            throw new HttpException('Cannot read uploaded CV.', 422, 'validation_error');
        }
        if (!str_starts_with($fileContent, '%PDF-')) {
            throw new HttpException('Only PDF CV is supported.', 422, 'validation_error');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) ($finfo->file($tmpName) ?: 'application/pdf');
        $allowedMimeTypes = ['application/pdf', 'application/x-pdf', 'application/octet-stream', 'binary/octet-stream'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            throw new HttpException('Only PDF CV is supported.', 422, 'validation_error');
        }

        $safeFileName = $this->normalizeCvFilename($originalName);
        $actorId = (int) (($request->attribute('auth_user')['employee_id'] ?? 0));

        $this->candidateCvs->upsertByCandidate($candidateId, [
            'original_filename' => $safeFileName,
            'mime_type' => 'application/pdf',
            'file_size' => $fileSize,
            'file_data' => $fileContent,
            'uploaded_by' => $actorId > 0 ? $actorId : null,
        ]);

        $this->candidates->updateById($candidateId, [
            'cv_url' => '/api/v1/recruitment-candidates/' . $candidateId . '/cv',
            'updated_by' => $actorId > 0 ? $actorId : null,
        ]);

        $meta = $this->candidateCvs->findMetaByCandidate($candidateId);
        if ($meta === null) {
            throw new HttpException('CV upload failed.', 500, 'upload_failed');
        }

        $meta['cv_download_url'] = '/api/v1/recruitment-candidates/' . $candidateId . '/cv';
        return $this->ok($meta, 'Candidate CV uploaded');
    }

    public function candidateDownloadCv(Request $request, array $params): array
    {
        $candidateId = (int) ($params['id'] ?? 0);
        $cv = $this->candidateCvs->findFileByCandidate($candidateId);
        if ($cv === null) {
            throw new HttpException('CV not found', 404, 'not_found');
        }

        $download = (string) ($request->query('download', '0') ?? '0');
        $disposition = $download === '1' ? 'attachment' : 'inline';
        $filename = $this->normalizeCvFilename((string) ($cv['original_filename'] ?? 'candidate-cv.pdf'));
        $body = (string) ($cv['file_data'] ?? '');

        return [
            'status' => 200,
            'response_type' => 'binary',
            'headers' => [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($body),
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
                'Cache-Control' => 'private, max-age=3600',
                'X-Content-Type-Options' => 'nosniff',
            ],
            'body' => $body,
        ];
    }

    public function interviewIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $status = $request->query('status');
        $interviewerId = $request->query('interviewer_id') !== null ? (int) $request->query('interviewer_id') : null;
        $candidateId = $request->query('candidate_id') !== null ? (int) $request->query('candidate_id') : null;

        $result = $this->interviews->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($status) ? $this->normalizeInterviewStatus($status) : null,
            $interviewerId,
            $candidateId
        );

        return $this->ok(
            $result['items'],
            'Interview list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function interviewStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'candidate_id' => ['required', 'integer'],
            'interviewer_id' => ['integer'],
            'interview_date' => ['required', 'date'],
            'interview_time' => ['required', 'string'],
            'interview_mode' => ['string'],
            'meeting_link' => ['string'],
            'location' => ['string'],
            'status' => ['string'],
            'result' => ['string'],
            'evaluation_notes' => ['string'],
        ]);

        $payload['interview_mode'] = $this->normalizeInterviewMode($payload['interview_mode'] ?? null);
        $payload['status'] = $this->normalizeInterviewStatus($payload['status'] ?? null);
        $payload['result'] = $this->normalizeInterviewResult($payload['result'] ?? null);
        $payload['manager_decision'] = 'PENDING';

        $candidateRouting = $this->candidates->findInterviewRouting((int) $payload['candidate_id']);
        if ($candidateRouting === null) {
            throw new HttpException('Candidate not found', 404, 'not_found');
        }
        $managerId = (int) ($candidateRouting['department_manager_id'] ?? 0);
        $payload['department_manager_id'] = $managerId > 0 ? $managerId : null;

        $actorId = (int) (($request->attribute('auth_user')['employee_id'] ?? 0));
        $payload['created_by'] = $actorId > 0 ? $actorId : null;
        $payload['updated_by'] = $actorId > 0 ? $actorId : null;

        $id = $this->interviews->create($payload);
        $this->syncCandidateStatusAfterInterviewScheduled(
            (int) $payload['candidate_id'],
            (string) ($candidateRouting['application_status'] ?? ''),
            $actorId > 0 ? $actorId : null
        );

        $saved = $this->interviews->findDetail($id);
        if (is_array($saved)) {
            $this->notifyDepartmentManagerForInterview($saved, $candidateRouting, $actorId > 0 ? $actorId : null);
        }

        return $this->created($saved, 'Interview created');
    }

    public function interviewUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->interviews->findDetail($id);
        if ($existing === null) {
            throw new HttpException('Interview not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'interviewer_id' => ['integer'],
            'department_manager_id' => ['integer'],
            'interview_date' => ['date'],
            'interview_time' => ['string'],
            'interview_mode' => ['string'],
            'meeting_link' => ['string'],
            'location' => ['string'],
            'status' => ['string'],
            'result' => ['string'],
            'evaluation_notes' => ['string'],
            'manager_review_notes' => ['string'],
            'manager_decision' => ['string'],
        ]);

        if (isset($payload['interview_mode'])) {
            $payload['interview_mode'] = $this->normalizeInterviewMode((string) $payload['interview_mode']);
        }
        if (isset($payload['status'])) {
            $payload['status'] = $this->normalizeInterviewStatus((string) $payload['status']);
        }
        if (isset($payload['result'])) {
            $payload['result'] = $this->normalizeInterviewResult((string) $payload['result']);
        }
        if (isset($payload['manager_decision'])) {
            $payload['manager_decision'] = $this->normalizeInterviewResult((string) $payload['manager_decision']);
            if (in_array($payload['manager_decision'], ['PASS', 'FAIL'], true)) {
                $payload['result'] = $payload['manager_decision'];
                if (!isset($payload['status'])) {
                    $payload['status'] = 'COMPLETED';
                }
                if (!isset($payload['reviewed_at'])) {
                    $payload['reviewed_at'] = date('Y-m-d H:i:s');
                }
            }
        }
        if (isset($payload['manager_review_notes']) && !isset($payload['evaluation_notes'])) {
            $payload['evaluation_notes'] = $payload['manager_review_notes'];
        }

        $actorId = (int) (($request->attribute('auth_user')['employee_id'] ?? 0));
        $payload['updated_by'] = $actorId > 0 ? $actorId : null;

        $this->interviews->updateById($id, $payload);
        $saved = $this->interviews->findDetail($id);
        if (is_array($saved)) {
            $this->syncCandidateStatusAfterInterviewResult($saved, $actorId > 0 ? $actorId : null);
        }

        return $this->ok($saved, 'Interview updated');
    }

    public function interviewManagerReview(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $context = $this->interviews->findReviewContext($id);
        if ($context === null) {
            throw new HttpException('Interview not found', 404, 'not_found');
        }

        $actorId = (int) (($request->attribute('auth_user')['employee_id'] ?? 0));
        $managerIds = array_values(array_unique(array_filter([
            (int) ($context['department_manager_id'] ?? 0),
            (int) ($context['current_department_manager_id'] ?? 0),
        ], static fn(int $value): bool => $value > 0)));

        if ($managerIds === [] || !in_array($actorId, $managerIds, true)) {
            throw new HttpException('Only department manager can submit interview review.', 403, 'forbidden');
        }

        $payload = Validator::validate($request->all(), [
            'manager_decision' => ['required', 'string'],
            'manager_review_notes' => ['string'],
        ]);

        $decision = $this->normalizeInterviewResult((string) $payload['manager_decision']);
        if (!in_array($decision, ['PASS', 'FAIL'], true)) {
            throw new HttpException('manager_decision must be PASS or FAIL.', 422, 'validation_error');
        }

        $updatePayload = [
            'department_manager_id' => $actorId,
            'manager_decision' => $decision,
            'result' => $decision,
            'status' => 'COMPLETED',
            'reviewed_at' => date('Y-m-d H:i:s'),
            'updated_by' => $actorId,
        ];

        if (isset($payload['manager_review_notes'])) {
            $updatePayload['manager_review_notes'] = $payload['manager_review_notes'];
            $updatePayload['evaluation_notes'] = $payload['manager_review_notes'];
        }

        $this->interviews->updateById($id, $updatePayload);
        $saved = $this->interviews->findDetail($id);
        if (is_array($saved)) {
            $this->syncCandidateStatusAfterInterviewResult($saved, $actorId);
        }

        return $this->ok($saved, 'Department manager review saved');
    }

    private function normalizeEmploymentType(mixed $value): string
    {
        $type = strtoupper(trim((string) ($value ?? 'FULL_TIME')));
        return in_array($type, ['FULL_TIME', 'PART_TIME', 'CONTRACT', 'INTERN'], true) ? $type : 'FULL_TIME';
    }

    private function normalizeRecruitmentPositionStatus(mixed $value): string
    {
        $status = strtoupper(trim((string) ($value ?? 'OPEN')));
        return in_array($status, ['OPEN', 'CLOSED', 'ON_HOLD'], true) ? $status : 'OPEN';
    }

    private function normalizeCandidateStatus(string $status): string
    {
        $mapped = strtoupper(trim($status));
        return match ($mapped) {
            'SCREENING', 'INTERVIEWING', 'PASSED', 'REJECTED', 'HIRED' => $mapped,
            'PASS' => 'PASSED',
            'FAIL' => 'REJECTED',
            default => 'NEW',
        };
    }

    private function normalizeManagerWorkflowStatus(string $status): string
    {
        $normalized = strtoupper(trim($status));
        return match ($normalized) {
            'APPROVED', 'APPROVE', 'PASS', 'PASSED', 'TP_DA_DUYET' => 'APPROVED',
            'REJECTED', 'REJECT', 'FAIL', 'TU_CHOI' => 'REJECTED',
            default => 'PENDING',
        };
    }

    private function normalizeInterviewMode(mixed $value): string
    {
        $mode = strtoupper(trim((string) ($value ?? 'ONLINE')));
        return in_array($mode, ['ONLINE', 'OFFLINE'], true) ? $mode : 'ONLINE';
    }

    private function normalizeInterviewStatus(mixed $value): string
    {
        $status = strtoupper(trim((string) ($value ?? 'SCHEDULED')));
        if (in_array($status, ['SCHEDULED', 'COMPLETED', 'CANCELED'], true)) {
            return $status;
        }

        return match ($status) {
            'SẮP DIỄN RA' => 'SCHEDULED',
            'ĐÃ XONG' => 'COMPLETED',
            'ĐÃ HỦY' => 'CANCELED',
            default => 'SCHEDULED',
        };
    }

    private function normalizeInterviewResult(mixed $value): string
    {
        $result = strtoupper(trim((string) ($value ?? 'PENDING')));
        return in_array($result, ['PASS', 'FAIL', 'PENDING'], true) ? $result : 'PENDING';
    }

    private function mapCandidateListWithCvAccess(array $items): array
    {
        $mapped = [];
        foreach ($items as $item) {
            if (is_array($item)) {
                $mapped[] = $this->mapCandidateWithCvAccess($item);
            }
        }
        return $mapped;
    }

    private function mapCandidateWithCvAccess(array $candidate): array
    {
        $candidateId = (int) ($candidate['candidate_id'] ?? 0);
        $hasCv = (int) ($candidate['has_cv'] ?? 0) === 1;
        if ($candidateId > 0 && $hasCv) {
            $downloadUrl = '/api/v1/recruitment-candidates/' . $candidateId . '/cv';
            $candidate['cv_download_url'] = $downloadUrl;
            if (!isset($candidate['cv_url']) || trim((string) $candidate['cv_url']) === '') {
                $candidate['cv_url'] = $downloadUrl;
            }
        }

        $applicationStatus = strtoupper(trim((string) ($candidate['application_status'] ?? 'NEW')));
        $workflowStatus = strtoupper(trim((string) ($candidate['workflow_status'] ?? 'PENDING')));

        $candidate['frontend_workflow_status'] = match (true) {
            $applicationStatus === 'REJECTED' => 'fail',
            in_array($applicationStatus, ['PASSED', 'HIRED'], true) => 'pass',
            $applicationStatus === 'INTERVIEWING' => 'interviewing',
            $workflowStatus === 'APPROVED' => 'mgr_approved',
            $workflowStatus === 'PENDING' && $applicationStatus === 'SCREENING' => 'pending_mgr',
            default => 'pending_hr',
        };

        return $candidate;
    }

    private function normalizeCvFilename(string $filename): string
    {
        $clean = trim($filename);
        $clean = str_replace(["\r", "\n"], '', $clean);
        $clean = preg_replace('/[^A-Za-z0-9._ -]/', '_', $clean) ?? $clean;
        if ($clean === '' || $clean === '.' || $clean === '..') {
            $clean = 'candidate-cv.pdf';
        }
        if (!str_ends_with(strtolower($clean), '.pdf')) {
            $clean .= '.pdf';
        }
        return $clean;
    }

    private function syncCandidateStatusAfterInterviewScheduled(int $candidateId, string $currentStatus, ?int $actorId): void
    {
        $status = strtoupper(trim($currentStatus));
        if (in_array($status, ['PASSED', 'REJECTED', 'HIRED'], true)) {
            return;
        }

        $payload = ['application_status' => 'INTERVIEWING'];
        if ($actorId !== null && $actorId > 0) {
            $payload['updated_by'] = $actorId;
        }
        $this->candidates->updateById($candidateId, $payload);
    }

    private function syncCandidateStatusAfterManagerWorkflow(
        int $candidateId,
        string $currentStatus,
        string $workflowStatus,
        ?int $actorId
    ): void {
        $status = strtoupper(trim($currentStatus));
        if (in_array($status, ['PASSED', 'REJECTED', 'HIRED'], true)) {
            return;
        }

        $next = match ($workflowStatus) {
            'REJECTED' => 'REJECTED',
            default => 'SCREENING',
        };

        $payload = ['application_status' => $next];
        if ($actorId !== null && $actorId > 0) {
            $payload['updated_by'] = $actorId;
        }
        $this->candidates->updateById($candidateId, $payload);
    }

    private function syncInterviewSuggestionFromManagerWorkflow(
        int $candidateId,
        array $reviewPayload,
        ?int $actorId
    ): void {
        $suggestedDate = (string) ($reviewPayload['suggested_interview_date'] ?? '');
        $suggestedTime = (string) ($reviewPayload['suggested_interview_time'] ?? '');
        if ($suggestedDate === '' || $suggestedTime === '') {
            return;
        }

        $existing = $this->interviews->findLatestByCandidate($candidateId);
        if ($existing !== null) {
            $update = [
                'interview_date' => $suggestedDate,
                'interview_time' => $suggestedTime,
                'status' => 'SCHEDULED',
            ];
            if ($actorId !== null && $actorId > 0) {
                $update['updated_by'] = $actorId;
            }
            $this->interviews->updateById((int) $existing['interview_id'], $update);
            return;
        }

        $insert = [
            'candidate_id' => $candidateId,
            'interviewer_id' => null,
            'department_manager_id' => $reviewPayload['manager_id'] ?? null,
            'interview_date' => $suggestedDate,
            'interview_time' => $suggestedTime,
            'interview_mode' => 'ONLINE',
            'status' => 'SCHEDULED',
            'result' => 'PENDING',
            'manager_decision' => 'PENDING',
        ];
        if ($actorId !== null && $actorId > 0) {
            $insert['created_by'] = $actorId;
            $insert['updated_by'] = $actorId;
        }
        $this->interviews->create($insert);
    }

    private function syncCandidateStatusAfterInterviewResult(array $interview, ?int $actorId): void
    {
        $candidateId = (int) ($interview['candidate_id'] ?? 0);
        if ($candidateId <= 0) {
            return;
        }

        $result = strtoupper(trim((string) ($interview['result'] ?? 'PENDING')));
        if (!in_array($result, ['PASS', 'FAIL'], true)) {
            return;
        }

        $payload = [
            'application_status' => $result === 'PASS' ? 'PASSED' : 'REJECTED',
        ];
        if ($actorId !== null && $actorId > 0) {
            $payload['updated_by'] = $actorId;
        }
        $this->candidates->updateById($candidateId, $payload);
    }

    private function notifyDepartmentManagerForInterview(array $interview, array $candidateRouting, ?int $actorId): void
    {
        $receiverId = (int) ($candidateRouting['department_manager_id'] ?? 0);
        if ($receiverId <= 0) {
            return;
        }

        $candidateName = (string) ($candidateRouting['candidate_name'] ?? 'Candidate');
        $positionName = (string) ($candidateRouting['position_name'] ?? 'Unknown position');
        $departmentName = (string) ($candidateRouting['department_name'] ?? 'Unknown department');
        $interviewDate = (string) ($interview['interview_date'] ?? '');
        $interviewTime = (string) ($interview['interview_time'] ?? '');
        $interviewId = (int) ($interview['interview_id'] ?? 0);

        $content = sprintf(
            'HR scheduled interview for %s (%s) on %s %s for %s.',
            $candidateName,
            $positionName,
            $interviewDate,
            $interviewTime,
            $departmentName
        );

        $payload = [
            'notification_type' => 'INTERVIEW_SCHEDULED',
            'title' => 'New interview schedule',
            'content' => $content,
            'receiver_id' => $receiverId,
            'department_id' => (int) ($candidateRouting['department_id'] ?? 0) > 0 ? (int) $candidateRouting['department_id'] : null,
            'reference_type' => null,
            'reference_id' => null,
            'action_url' => $interviewId > 0 ? '/recruitment/interviews/' . $interviewId : null,
        ];

        if ($actorId !== null && $actorId > 0) {
            $payload['sender_id'] = $actorId;
        }

        $this->notifications->create($payload);
    }
}
