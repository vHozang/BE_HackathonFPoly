<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Validator;
use App\Models\Asset;
use App\Models\AssetAssignment;

class AssetController extends Controller
{
    private Asset $assets;
    private AssetAssignment $assignments;

    public function __construct()
    {
        $this->assets = new Asset();
        $this->assignments = new AssetAssignment();
    }

    public function assetIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $search = $request->query('q');
        $status = $request->query('status');

        $result = $this->assets->paginateList(
            $paging['offset'],
            $paging['per_page'],
            is_string($search) ? $search : null,
            is_string($status) ? $status : null
        );

        return $this->ok(
            $result['items'],
            'Asset list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function assetShow(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $asset = $this->assets->find($id);
        if ($asset === null) {
            throw new HttpException('Asset not found', 404, 'not_found');
        }
        return $this->ok($asset, 'Asset detail');
    }

    public function assetStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'asset_code' => ['required', 'string'],
            'asset_name' => ['required', 'string'],
            'category_id' => ['integer'],
            'supplier_id' => ['integer'],
            'serial_number' => ['string'],
            'inventory_number' => ['string'],
            'purchase_date' => ['date'],
            'purchase_price' => ['numeric'],
            'status' => ['string'],
            'location_id' => ['integer'],
        ]);

        $id = $this->assets->create($payload);
        return $this->created($this->assets->find($id), 'Asset created');
    }

    public function assetUpdate(Request $request, array $params): array
    {
        $id = (int) ($params['id'] ?? 0);
        $existing = $this->assets->find($id);
        if ($existing === null) {
            throw new HttpException('Asset not found', 404, 'not_found');
        }

        $payload = Validator::validate($request->all(), [
            'asset_name' => ['string'],
            'category_id' => ['integer'],
            'supplier_id' => ['integer'],
            'serial_number' => ['string'],
            'inventory_number' => ['string'],
            'purchase_date' => ['date'],
            'purchase_price' => ['numeric'],
            'status' => ['string'],
            'location_id' => ['integer'],
            'condition_note' => ['string'],
            'notes' => ['string'],
        ]);

        $this->assets->updateById($id, $payload);
        return $this->ok($this->assets->find($id), 'Asset updated');
    }

    public function assignmentIndex(Request $request): array
    {
        $paging = Paginator::resolve($request);
        $employeeId = $request->query('employee_id') !== null ? (int) $request->query('employee_id') : null;
        $status = $request->query('status');

        $result = $this->assignments->paginateList(
            $paging['offset'],
            $paging['per_page'],
            $employeeId,
            is_string($status) ? $status : null
        );

        return $this->ok(
            $result['items'],
            'Asset assignment list',
            Paginator::meta($result['total'], $paging['page'], $paging['per_page'])
        );
    }

    public function assignmentStore(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'asset_id' => ['required', 'integer'],
            'employee_id' => ['required', 'integer'],
            'assigned_date' => ['required', 'date'],
            'expected_return_date' => ['date'],
            'status' => ['string'],
            'assignment_notes' => ['string'],
            'condition_before' => ['string'],
        ]);

        $authUser = $request->attribute('auth_user');
        $actor = (int) ($authUser['employee_id'] ?? 1);
        $payload['assigned_by'] = $actor;
        $id = $this->assignments->create($payload);
        return $this->created($this->assignments->find($id), 'Asset assignment created');
    }
}
