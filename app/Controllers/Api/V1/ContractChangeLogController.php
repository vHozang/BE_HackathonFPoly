<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\ContractChangeLog;

class ContractChangeLogController extends Controller
{
    private ContractChangeLog $logs;

    public function __construct()
    {
        $this->logs = new ContractChangeLog();
    }

    public function index(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');

        $result = $this->logs->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null
        );

        return $this->ok(
            $result['items'],
            'Contract change log list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function store(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'contract_id' => ['integer'],
            'contract_no' => ['required', 'string'],
            'employee_name' => ['required', 'string'],
            'action_type' => ['required', 'string'],
            'content' => ['string'],
            'icon' => ['string'],
            'bg_class' => ['string'],
            'notes' => ['string'],
        ]);

        $actionType = strtoupper(trim((string) ($payload['action_type'] ?? 'UPDATE')));
        $contractNo = trim((string) ($payload['contract_no'] ?? ''));
        $employeeName = trim((string) ($payload['employee_name'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));

        [$defaultIcon, $defaultBg] = $this->defaultVisual($actionType);

        if ($content === '') {
            $content = $this->buildContent($actionType, $contractNo, $employeeName);
        }

        $authUser = $request->attribute('auth_user');
        $createdBy = (int) ($authUser['employee_id'] ?? 0);

        $id = $this->logs->create([
            'contract_id' => isset($payload['contract_id']) ? (int) $payload['contract_id'] : null,
            'contract_no' => $contractNo,
            'employee_name' => $employeeName,
            'action_type' => $actionType,
            'content' => $content,
            'icon' => (string) ($payload['icon'] ?? $defaultIcon),
            'bg_class' => (string) ($payload['bg_class'] ?? $defaultBg),
            'notes' => $payload['notes'] ?? null,
            'created_by' => $createdBy > 0 ? $createdBy : null,
        ]);

        return $this->created($this->logs->findDetail($id), 'Contract change log created');
    }

    private function defaultVisual(string $actionType): array
    {
        return match ($actionType) {
            'CREATE', 'SIGN' => ['add', 'bg-blue-500'],
            'EXTEND' => ['restore', 'bg-green-500'],
            'TERMINATE' => ['delete', 'bg-red-500'],
            default => ['edit', 'bg-amber-500'],
        };
    }

    private function buildContent(string $actionType, string $contractNo, string $employeeName): string
    {
        return match ($actionType) {
            'CREATE', 'SIGN' => sprintf('Ky moi hop dong %s - %s', $contractNo, $employeeName),
            'EXTEND' => sprintf('Gia han hop dong %s - %s', $contractNo, $employeeName),
            'TERMINATE' => sprintf('Thanh ly hop dong %s - %s', $contractNo, $employeeName),
            default => sprintf('Cap nhat hop dong %s - %s', $contractNo, $employeeName),
        };
    }
}
