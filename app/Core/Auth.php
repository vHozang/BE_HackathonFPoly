<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    public static function attempt(string $companyEmail, string $password): array
    {
        $sql = 'SELECT employee_id, employee_code, full_name, company_email, status, password_hash
                FROM employees
                WHERE company_email = :company_email
                LIMIT 1';

        $stmt = Database::connection()->prepare($sql);
        $stmt->execute([
            'company_email' => $companyEmail,
        ]);

        $user = $stmt->fetch();
        if ($user === false) {
            throw new HttpException('Invalid credentials', 401, 'invalid_credentials');
        }

        $passwordHash = (string) ($user['password_hash'] ?? '');
        if ($passwordHash !== '') {
            if (!password_verify($password, $passwordHash)) {
                throw new HttpException('Invalid credentials', 401, 'invalid_credentials');
            }
        } else {
            // Backward compatibility: existing seeded accounts can login once with employee_code as password.
            if (!hash_equals((string) $user['employee_code'], $password)) {
                throw new HttpException('Invalid credentials', 401, 'invalid_credentials');
            }

            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $updateStmt = Database::connection()->prepare(
                'UPDATE employees SET password_hash = :password_hash WHERE employee_id = :employee_id'
            );
            $updateStmt->execute([
                'password_hash' => $newHash,
                'employee_id' => (int) $user['employee_id'],
            ]);
            $user['password_hash'] = $newHash;
        }

        return self::buildAuthResult($user);
    }

    public static function issueTokenForEmployee(int $employeeId): array
    {
        if ($employeeId <= 0) {
            throw new HttpException('Invalid employee id', 401, 'invalid_token');
        }

        $stmt = Database::connection()->prepare(
            'SELECT employee_id, employee_code, full_name, company_email, status, password_hash FROM employees WHERE employee_id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $employeeId]);
        $user = $stmt->fetch();
        if ($user === false) {
            throw new HttpException('User not found', 401, 'invalid_token');
        }

        return self::buildAuthResult($user);
    }

    private static function buildAuthResult(array $user): array
    {
        $roles = self::rolesForEmployee((int) $user['employee_id']);
        $roleIds = array_map(static fn(array $r): int => (int) $r['role_id'], $roles);
        $permissionMatrix = self::permissionMatrixForRoles($roleIds);
        $permissions = array_keys($permissionMatrix);
        $managedDepartments = Hierarchy::managedDepartmentIds((int) $user['employee_id']);
        $hierarchyEmployees = Hierarchy::subordinateEmployeeIds((int) $user['employee_id']);

        $config = require base_path('config/app.php');
        $issuedAt = time();
        $expiresAt = $issuedAt + (int) $config['jwt']['ttl'];

        $payload = [
            'iss' => $config['jwt']['issuer'],
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expiresAt,
            'sub' => (int) $user['employee_id'],
            'employee_code' => $user['employee_code'],
            'roles' => array_column($roles, 'role_code'),
            'permissions' => $permissions,
            'hierarchy_employee_ids' => $hierarchyEmployees,
        ];

        $token = Jwt::encode($payload, (string) $config['jwt']['secret']);

        return [
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) $config['jwt']['ttl'],
            'user' => [
                'employee_id' => (int) $user['employee_id'],
                'employee_code' => $user['employee_code'],
                'full_name' => $user['full_name'],
                'company_email' => $user['company_email'],
                'status' => $user['status'],
                'roles' => $roles,
                'permissions' => $permissions,
                'permission_matrix' => $permissionMatrix,
                'managed_department_ids' => $managedDepartments,
                'hierarchy_employee_ids' => $hierarchyEmployees,
            ],
        ];
    }

    public static function userFromToken(string $token): array
    {
        $config = require base_path('config/app.php');
        $payload = Jwt::decode($token, (string) $config['jwt']['secret']);

        $employeeId = (int) ($payload['sub'] ?? 0);
        if ($employeeId <= 0) {
            throw new HttpException('Invalid token subject', 401, 'invalid_token');
        }

        $stmt = Database::connection()->prepare('SELECT employee_id, employee_code, full_name, company_email, status FROM employees WHERE employee_id = :id LIMIT 1');
        $stmt->execute(['id' => $employeeId]);
        $user = $stmt->fetch();
        if ($user === false) {
            throw new HttpException('User not found', 401, 'invalid_token');
        }

        $roles = self::rolesForEmployee($employeeId);
        $roleIds = array_map(static fn(array $r): int => (int) $r['role_id'], $roles);
        $permissionMatrix = self::permissionMatrixForRoles($roleIds);
        $permissions = array_keys($permissionMatrix);
        $managedDepartments = Hierarchy::managedDepartmentIds($employeeId);
        $hierarchyEmployees = Hierarchy::subordinateEmployeeIds($employeeId);

        $user['employee_id'] = (int) $user['employee_id'];
        $user['roles'] = $roles;
        $user['permissions'] = $permissions;
        $user['permission_matrix'] = $permissionMatrix;
        $user['managed_department_ids'] = $managedDepartments;
        $user['hierarchy_employee_ids'] = $hierarchyEmployees;
        $user['token_payload'] = $payload;
        return $user;
    }

    private static function rolesForEmployee(int $employeeId): array
    {
        $sql = 'SELECT r.role_id, r.role_code, r.role_name
                FROM employee_roles er
                JOIN roles r ON r.role_id = er.role_id
                WHERE er.employee_id = :employee_id
                  AND er.is_active = 1
                  AND (er.expiry_date IS NULL OR er.expiry_date >= CURDATE())';
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(['employee_id' => $employeeId]);
        return $stmt->fetchAll() ?: [];
    }

    private static function permissionMatrixForRoles(array $roleIds): array
    {
        if ($roleIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $sql = "SELECT p.permission_code,
                       MAX(rp.can_access) AS can_access,
                       MAX(rp.can_create) AS can_create,
                       MAX(rp.can_edit) AS can_edit,
                       MAX(rp.can_delete) AS can_delete,
                       MAX(rp.can_approve) AS can_approve,
                       MAX(rp.can_export) AS can_export
                FROM role_permissions rp
                JOIN permissions p ON p.permission_id = rp.permission_id
                WHERE rp.role_id IN ($placeholders)
                GROUP BY p.permission_code
                HAVING MAX(rp.can_access) = 1";
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute(array_values($roleIds));

        $rows = $stmt->fetchAll() ?: [];
        $matrix = [];
        foreach ($rows as $row) {
            $code = (string) $row['permission_code'];
            $matrix[$code] = [
                'can_access' => (bool) $row['can_access'],
                'can_create' => (bool) $row['can_create'],
                'can_edit' => (bool) $row['can_edit'],
                'can_delete' => (bool) $row['can_delete'],
                'can_approve' => (bool) $row['can_approve'],
                'can_export' => (bool) $row['can_export'],
            ];
        }

        return $matrix;
    }

    public static function hasPermission(array $authUser, string $permissionCode, string $action = 'access'): bool
    {
        if (self::isPrivileged($authUser)) {
            return true;
        }

        $matrix = $authUser['permission_matrix'] ?? [];
        if (!isset($matrix[$permissionCode])) {
            return false;
        }

        $flag = match (strtolower($action)) {
            'create' => 'can_create',
            'edit' => 'can_edit',
            'delete' => 'can_delete',
            'approve' => 'can_approve',
            'export' => 'can_export',
            default => 'can_access',
        };

        return (bool) ($matrix[$permissionCode][$flag] ?? false);
    }

    public static function isPrivileged(array $authUser): bool
    {
        $roleCodes = array_map(
            static fn(array|string $role): string => is_array($role) ? (string) ($role['role_code'] ?? '') : (string) $role,
            $authUser['roles'] ?? []
        );

        return in_array('ADMIN', $roleCodes, true) || in_array('HR', $roleCodes, true);
    }
}
