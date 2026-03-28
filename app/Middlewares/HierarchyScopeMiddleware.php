<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Hierarchy;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

class HierarchyScopeMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $employeeQueryKey = 'employee_id',
        private readonly bool $allowHierarchy = true
    ) {
    }

    public function handle(Request $request, callable $next): array
    {
        $authUser = $request->attribute('auth_user');
        if (!is_array($authUser)) {
            throw new HttpException('Unauthorized', 401, 'unauthorized');
        }

        if (Auth::isPrivileged($authUser)) {
            return $next($request);
        }

        $selfId = (int) ($authUser['employee_id'] ?? 0);
        $requestedEmployeeId = $request->query($this->employeeQueryKey);
        $scope = (string) $request->query('scope', 'self');

        if ($requestedEmployeeId !== null && $requestedEmployeeId !== '') {
            $target = (int) $requestedEmployeeId;
            if (!Hierarchy::canAccessEmployee($authUser, $target, $this->allowHierarchy)) {
                throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
            }
            $request->setAttribute('scope_employee_ids', [$target]);
            return $next($request);
        }

        $ids = [$selfId];
        if ($this->allowHierarchy && strtolower($scope) === 'hierarchy') {
            $ids = array_values(array_unique(array_merge($ids, $authUser['hierarchy_employee_ids'] ?? [])));
        }

        $request->setAttribute('scope_employee_ids', $ids);
        return $next($request);
    }
}
