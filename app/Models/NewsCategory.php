<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class NewsCategory extends Model
{
    protected string $table = 'news_categories';
    protected string $primaryKey = 'category_id';
    protected array $fillable = [
        'category_code',
        'category_name',
        'description',
        'status',
    ];

    public function paginateList(int $offset, int $limit, ?string $search = null): array
    {
        $where = [];
        $params = [];
        if ($search !== null && $search !== '') {
            $where[] = '(nc.category_code LIKE :search OR nc.category_name LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT nc.*
                FROM news_categories nc
                $whereSql
                ORDER BY nc.category_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total FROM news_categories nc $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);
        return ['items' => $items, 'total' => $total];
    }
}
