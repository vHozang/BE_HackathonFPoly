<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class ServiceCategory extends Model
{
    protected string $table = 'service_categories';
    protected string $primaryKey = 'category_id';
    protected array $fillable = [
        'category_code',
        'category_name',
        'description',
        'status',
    ];

    public function listActive(): array
    {
        $sql = "SELECT category_id, category_code, category_name, description, status
                FROM service_categories
                WHERE status = 1
                ORDER BY category_name ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
