<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Position;

class PositionController extends Controller
{
    private Position $positions;

    public function __construct()
    {
        $this->positions = new Position();
    }

    public function index(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');
        $group = $request->query('group');

        $resolvedStatus = null;
        if ($status !== null && $status !== '') {
            $resolvedStatus = in_array((string) $status, ['1', 'true', 'active'], true) ? 1 : 0;
        }

        $result = $this->positions->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            $resolvedStatus,
            is_string($group) ? $group : null
        );

        return $this->ok(
            $result['items'],
            'Position list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function show(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->positions->find($id);
        if ($item === null) {
            throw new HttpException('Position not found', 404, 'not_found');
        }
        return $this->ok($item, 'Position detail');
    }

    public function store(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'position_code' => ['required', 'string'],
            'position_name' => ['required', 'string'],
            'position_group' => ['string'],
            'position_level' => ['string'],
            'job_description' => ['string'],
            'requirements' => ['string'],
            'salary_range_min' => ['numeric'],
            'salary_range_max' => ['numeric'],
            'status' => ['boolean'],
        ]);

        $id = $this->positions->create($payload);
        return $this->created($this->positions->find($id), 'Position created');
    }

    public function update(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->positions->find($id);
        if ($existing === null) {
            throw new HttpException('Position not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'position_name' => ['string'],
            'position_group' => ['string'],
            'position_level' => ['string'],
            'job_description' => ['string'],
            'requirements' => ['string'],
            'salary_range_min' => ['numeric'],
            'salary_range_max' => ['numeric'],
            'status' => ['boolean'],
        ]);

        $this->positions->updateById($id, $payload);
        return $this->ok($this->positions->find($id), 'Position updated');
    }

    public function destroy(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->positions->find($id);
        if ($existing === null) {
            throw new HttpException('Position not found', 404, 'not_found');
        }
        $this->positions->deleteById($id);
        return $this->ok(null, 'Position deleted');
    }
}
