<?php
declare(strict_types=1);

namespace App\Core;

class Hierarchy
{
    public static function managedDepartmentIds(int $managerEmployeeId): array
    {
        $sql = "WITH RECURSIVE managed AS (
                    SELECT d.department_id
                    FROM departments d
                    WHERE d.manager_id = :manager_id
                    UNION ALL
                    SELECT c.department_id
                    FROM departments c
                    INNER JOIN managed m ON c.parent_department_id = m.department_id
                )
                SELECT DISTINCT department_id
                FROM managed";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['manager_id' => $managerEmployeeId]);
        $rows = $stmt->fetchAll() ?: [];

        return array_values(array_map(static fn(array $r): int => (int) $r['department_id'], $rows));
    }

    public static function subordinateEmployeeIds(int $managerEmployeeId): array
    {
        $departmentIds = self::managedDepartmentIds($managerEmployeeId);
        if ($departmentIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($departmentIds), '?'));
        $sql = "SELECT DISTINCT eh.employee_id
                FROM employment_histories eh
                WHERE eh.is_current = 1
                  AND eh.department_id IN ($placeholders)";

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($departmentIds);
        $rows = $stmt->fetchAll() ?: [];

        $ids = array_values(array_map(static fn(array $r): int => (int) $r['employee_id'], $rows));
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id !== $managerEmployeeId));
        return array_values(array_unique($ids));
    }

    public static function canAccessEmployee(array $authUser, int $targetEmployeeId, bool $allowHierarchy = true): bool
    {
        $selfId = (int) ($authUser['employee_id'] ?? 0);
        if ($selfId <= 0) {
            return false;
        }
        if ($selfId === $targetEmployeeId) {
            return true;
        }
        if (Auth::isPrivileged($authUser)) {
            return true;
        }
        if (!$allowHierarchy) {
            return false;
        }

        $hierarchyIds = $authUser['hierarchy_employee_ids'] ?? [];
        return in_array($targetEmployeeId, $hierarchyIds, true);
    }
}
