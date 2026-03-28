<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class Asset extends Model
{
    protected string $table = 'assets';
    protected string $primaryKey = 'asset_id';
    protected array $fillable = [
        'asset_code',
        'asset_name',
        'category_id',
        'supplier_id',
        'serial_number',
        'inventory_number',
        'brand',
        'model',
        'color',
        'purchase_date',
        'purchase_price',
        'original_price',
        'current_value',
        'warranty_expiry',
        'location_id',
        'status',
        'condition_note',
        'specifications',
        'image_url',
        'notes',
    ];

    public function paginateList(int $offset, int $limit, ?string $search = null, ?string $status = null): array
    {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(a.asset_code LIKE :search OR a.asset_name LIKE :search OR a.serial_number LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== null && $status !== '') {
            $where[] = 'a.status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT a.*, ac.category_name, al.location_name
                FROM assets a
                LEFT JOIN asset_categories ac ON ac.category_id = a.category_id
                LEFT JOIN asset_locations al ON al.location_id = a.location_id
                $whereSql
                ORDER BY a.asset_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total FROM assets a $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }
}
