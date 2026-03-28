<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

class PermissionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $permissionCode,
        private readonly string $action = 'access'
    ) {
    }

    public function handle(Request $request, callable $next): array
    {
        $authUser = $request->attribute('auth_user');
        if (!is_array($authUser)) {
            throw new HttpException('Unauthorized', 401, 'unauthorized');
        }

        if (!Auth::hasPermission($authUser, $this->permissionCode, $this->action)) {
            throw new HttpException(
                sprintf('Permission denied: %s (%s)', $this->permissionCode, $this->action),
                403,
                'forbidden'
            );
        }

        return $next($request);
    }
}
