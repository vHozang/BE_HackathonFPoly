<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class ContractType extends Model
{
    protected string $table = 'contract_types';
    protected string $primaryKey = 'contract_type_id';
    protected array $fillable = [
        'contract_type_code',
        'contract_type_name',
        'description',
        'is_probation',
        'max_duration_months',
        'status',
    ];

    public function listActive(): array
    {
        $sql = "SELECT contract_type_id,
                       contract_type_code,
                       contract_type_name,
                       is_probation,
                       max_duration_months,
                       status
                FROM contract_types
                WHERE status = 1
                ORDER BY contract_type_id ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
