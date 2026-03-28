<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

abstract class Model
{
    protected PDO $db;
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $fillable = [];

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function find(int $id): ?array
    {
        $sql = sprintf('SELECT * FROM %s WHERE %s = :id LIMIT 1', $this->table, $this->primaryKey);
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function create(array $data): int
    {
        $payload = $this->filterFillable($data);
        if ($payload === []) {
            throw new HttpException('No valid fields to create', 422, 'validation_error');
        }

        $columns = array_keys($payload);
        $params = array_map(static fn(string $c): string => ':' . $c, $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $params)
        );

        $stmt = $this->db->prepare($sql);
        $this->bindPayload($stmt, $payload);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    public function updateById(int $id, array $data): bool
    {
        $payload = $this->filterFillable($data);
        if ($payload === []) {
            throw new HttpException('No valid fields to update', 422, 'validation_error');
        }

        $sets = [];
        foreach (array_keys($payload) as $column) {
            $sets[] = sprintf('%s = :%s', $column, $column);
        }
        $payload['_id'] = $id;

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s = :_id',
            $this->table,
            implode(', ', $sets),
            $this->primaryKey
        );

        $stmt = $this->db->prepare($sql);
        $this->bindPayload($stmt, $payload);
        return $stmt->execute();
    }

    public function deleteById(int $id): bool
    {
        $sql = sprintf('DELETE FROM %s WHERE %s = :id', $this->table, $this->primaryKey);
        $stmt = $this->db->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    protected function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    protected function bindPayload(\PDOStatement $stmt, array $payload): void
    {
        foreach ($payload as $key => $value) {
            $param = ':' . $key;

            if ($value === null) {
                $stmt->bindValue($param, null, PDO::PARAM_NULL);
                continue;
            }

            if (is_bool($value)) {
                $stmt->bindValue($param, $value ? 1 : 0, PDO::PARAM_INT);
                continue;
            }

            if (is_int($value)) {
                $stmt->bindValue($param, $value, PDO::PARAM_INT);
                continue;
            }

            $stmt->bindValue($param, (string) $value, PDO::PARAM_STR);
        }
    }
}
