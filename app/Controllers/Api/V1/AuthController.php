<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Validator;

class AuthController extends Controller
{
    public function login(Request $request): array
    {
        $payload = Validator::validate($request->all(), [
            'company_email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $result = Auth::attempt(
            (string) $payload['company_email'],
            (string) $payload['password']
        );

        return $this->ok($result, 'Login successful');
    }

    public function me(Request $request): array
    {
        $user = $request->attribute('auth_user');
        return $this->ok($user, 'Current user');
    }

    public function refresh(Request $request): array
    {
        $token = $request->bearerToken();
        $user = $request->attribute('auth_user');
        $result = Auth::issueTokenForEmployee((int) ($user['employee_id'] ?? 0));
        if ($token === null) {
            return $this->ok($result, 'Token refreshed');
        }
        return $this->ok($result, 'Token refreshed');
    }

    public function hierarchy(Request $request): array
    {
        $authUser = $request->attribute('auth_user');
        $employeeIds = array_values(array_unique(array_merge(
            [(int) ($authUser['employee_id'] ?? 0)],
            $authUser['hierarchy_employee_ids'] ?? []
        )));

        $items = [];
        if ($employeeIds !== []) {
            $placeholders = implode(',', array_fill(0, count($employeeIds), '?'));
            $sql = "SELECT e.employee_id, e.employee_code, e.full_name, d.department_name
                    FROM employees e
                    LEFT JOIN employment_histories eh ON eh.employee_id = e.employee_id AND eh.is_current = 1
                    LEFT JOIN departments d ON d.department_id = eh.department_id
                    WHERE e.employee_id IN ($placeholders)
                    ORDER BY e.employee_id";
            $stmt = Database::connection()->prepare($sql);
            $stmt->execute($employeeIds);
            $items = $stmt->fetchAll() ?: [];
        }

        return $this->ok([
            'managed_department_ids' => $authUser['managed_department_ids'] ?? [],
            'hierarchy_employee_ids' => $authUser['hierarchy_employee_ids'] ?? [],
            'employees' => $items,
        ], 'Hierarchy scope');
    }
}
