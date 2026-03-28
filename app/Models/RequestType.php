<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class RequestType extends Model
{
    protected string $table = 'request_types';
    protected string $primaryKey = 'request_type_id';
    protected array $fillable = [
        'request_type_code',
        'request_type_name',
        'category',
        'requires_approval',
        'approval_flow_id',
        'form_template',
        'description',
        'is_active',
    ];

    public function paginateList(int $offset, int $limit, ?string $search = null, ?string $category = null): array
    {
        $where = [];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(rt.request_type_code LIKE :search OR rt.request_type_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($category !== null && $category !== '') {
            $where[] = 'rt.category = :category';
            $params['category'] = $category;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT rt.*, af.flow_name
                FROM request_types rt
                LEFT JOIN approval_flows af ON af.approval_flow_id = rt.approval_flow_id
                $whereSql
                ORDER BY rt.request_type_id ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total FROM request_types rt $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);

        return ['items' => $items, 'total' => $total];
    }
}
