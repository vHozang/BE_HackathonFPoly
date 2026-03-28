<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Hierarchy;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

class HierarchyEmployeeBodyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $fieldName = 'employee_id',
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

        $target = $request->input($this->fieldName);
        if ($target !== null && $target !== '') {
            $targetId = (int) $target;
            if (!Hierarchy::canAccessEmployee($authUser, $targetId, $this->allowHierarchy)) {
                throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
            }
        } else {
            $request->setAttribute('forced_employee_id', (int) ($authUser['employee_id'] ?? 0));
        }

        return $next($request);
    }
}
