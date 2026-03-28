<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new HttpException('Missing bearer token', 401, 'unauthorized');
        }

        $user = Auth::userFromToken($token);
        $request->setAttribute('auth_user', $user);

        return $next($request);
    }
}
