<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Department;

class DepartmentController extends Controller
{
    private Department $departments;

    public function __construct()
    {
        $this->departments = new Department();
    }

    public function index(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $managerId = $request->query('manager_id') !== null ? (int) $request->query('manager_id') : null;

        $result = $this->departments->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            $managerId
        );

        return $this->ok(
            $result['items'],
            'Department list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function show(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $department = $this->departments->find($id);
        if ($department === null) {
            throw new HttpException('Department not found', 404, 'not_found');
        }
        return $this->ok($department, 'Department detail');
    }

    public function store(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'department_code' => ['required', 'string'],
            'department_name' => ['required', 'string'],
            'parent_department_id' => ['integer'],
            'manager_id' => ['integer'],
            'description' => ['string'],
            'status' => ['boolean'],
        ]);

        $id = $this->departments->create($payload);
        return $this->created($this->departments->find($id), 'Department created');
    }

    public function update(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->departments->find($id);
        if ($existing === null) {
            throw new HttpException('Department not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'department_name' => ['string'],
            'parent_department_id' => ['integer'],
            'manager_id' => ['integer'],
            'description' => ['string'],
            'status' => ['boolean'],
        ]);
        $this->departments->updateById($id, $payload);
        return $this->ok($this->departments->find($id), 'Department updated');
    }

    public function destroy(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->departments->find($id);
        if ($existing === null) {
            throw new HttpException('Department not found', 404, 'not_found');
        }
        $this->departments->deleteById($id);
        return $this->ok(null, 'Department deleted');
    }
}
