<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\RequestType;

class RequestTypeController extends Controller
{
    private RequestType $requestTypes;

    public function __construct()
    {
        $this->requestTypes = new RequestType();
    }

    public function index(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $category = $request->query('category');

        $result = $this->requestTypes->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($category) ? $category : null
        );

        return $this->ok(
            $result['items'],
            'Request type list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function show(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $item = $this->requestTypes->find($id);
        if ($item === null) {
            throw new HttpException('Request type not found', 404, 'not_found');
        }
        return $this->ok($item, 'Request type detail');
    }

    public function store(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'request_type_code' => ['required', 'string'],
            'request_type_name' => ['required', 'string'],
            'category' => ['required', 'string'],
            'requires_approval' => ['boolean'],
            'approval_flow_id' => ['integer'],
            'form_template' => ['string'],
            'description' => ['string'],
            'is_active' => ['boolean'],
        ]);

        $id = $this->requestTypes->create($payload);
        return $this->created($this->requestTypes->find($id), 'Request type created');
    }

    public function update(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->requestTypes->find($id);
        if ($existing === null) {
            throw new HttpException('Request type not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'request_type_name' => ['string'],
            'category' => ['string'],
            'requires_approval' => ['boolean'],
            'approval_flow_id' => ['integer'],
            'form_template' => ['string'],
            'description' => ['string'],
            'is_active' => ['boolean'],
        ]);

        $this->requestTypes->updateById($id, $payload);
        return $this->ok($this->requestTypes->find($id), 'Request type updated');
    }

    public function destroy(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->requestTypes->find($id);
        if ($existing === null) {
            throw new HttpException('Request type not found', 404, 'not_found');
        }
        $this->requestTypes->deleteById($id);
        return $this->ok(null, 'Request type deleted');
    }
}
