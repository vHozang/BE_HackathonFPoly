<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class News extends Model
{
    protected string $table = 'news';
    protected string $primaryKey = 'news_id';
    protected array $fillable = [
        'news_code',
        'category_id',
        'title',
        'summary',
        'content',
        'priority',
        'is_important',
        'is_pinned',
        'published_date',
        'expiry_date',
        'published_by',
        'department_id',
        'position_id',
        'attachment_url',
        'image_url',
        'view_count',
        'status',
        'created_by',
        'updated_by',
    ];

    public function paginateList(int $offset, int $limit, ?string $search = null, ?string $status = null): array
    {
        $where = [];
        $params = [];
        if ($search !== null && $search !== '') {
            $where[] = '(n.news_code LIKE :search OR n.title LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($status !== null && $status !== '') {
            $where[] = 'n.status = :status';
            $params['status'] = $status;
        }

        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT n.*, nc.category_name, e.full_name AS published_by_name
                FROM news n
                LEFT JOIN news_categories nc ON nc.category_id = n.category_id
                LEFT JOIN employees e ON e.employee_id = n.published_by
                $whereSql
                ORDER BY n.is_pinned DESC, n.published_date DESC, n.news_id DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll() ?: [];

        $countSql = "SELECT COUNT(*) AS total
                     FROM news n
                     LEFT JOIN news_categories nc ON nc.category_id = n.category_id
                     LEFT JOIN employees e ON e.employee_id = n.published_by
                     $whereSql";
        $countStmt = $this->db->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $countStmt->execute();
        $total = (int) ($countStmt->fetch()['total'] ?? 0);
        return ['items' => $items, 'total' => $total];
    }
}
