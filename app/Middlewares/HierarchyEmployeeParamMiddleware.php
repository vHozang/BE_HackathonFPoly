<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\Hierarchy;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

class HierarchyEmployeeParamMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $paramName = 'id',
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

        $params = $request->attribute('route_params', []);
        $target = (int) ($params[$this->paramName] ?? 0);
        if ($target > 0 && !Hierarchy::canAccessEmployee($authUser, $target, $this->allowHierarchy)) {
            throw new HttpException('Hierarchy scope denied', 403, 'forbidden');
        }

        return $next($request);
    }
}
