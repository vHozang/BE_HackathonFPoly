<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

class SystemConfig extends Model
{
    protected string $table = 'system_configs';
    protected string $primaryKey = 'config_id';
    protected array $fillable = [
        'config_key',
        'config_value',
        'config_type',
        'description',
        'module',
    ];

    public function findByKeys(array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($keys) as $index => $key) {
            $param = ':key_' . $index;
            $placeholders[] = $param;
            $params['key_' . $index] = (string) $key;
        }

        $sql = 'SELECT config_key, config_value, config_type
                FROM system_configs
                WHERE config_key IN (' . implode(', ', $placeholders) . ')';
        $stmt = $this->db->prepare($sql);
        foreach ($params as $param => $value) {
            $stmt->bindValue(':' . $param, $value, PDO::PARAM_STR);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll() ?: [];
        $mapped = [];
        foreach ($rows as $row) {
            $mapped[(string) $row['config_key']] = $row;
        }

        return $mapped;
    }

    public function upsert(string $key, ?string $value, string $type = 'TEXT', ?string $description = null, ?string $module = null): void
    {
        $sql = "INSERT INTO system_configs (config_key, config_value, config_type, description, module)
                VALUES (:config_key, :config_value, :config_type, :description, :module)
                ON DUPLICATE KEY UPDATE
                    config_value = VALUES(config_value),
                    config_type = VALUES(config_type),
                    description = VALUES(description),
                    module = VALUES(module),
                    updated_at = CURRENT_TIMESTAMP";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'config_key' => $key,
            'config_value' => $value,
            'config_type' => $type,
            'description' => $description,
            'module' => $module,
        ]);
    }
}
