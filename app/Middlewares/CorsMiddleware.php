<?php
declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): array
    {
        $config = require base_path('config/cors.php');

        header('Access-Control-Allow-Origin: ' . $config['allow_origin']);
        header('Access-Control-Allow-Methods: ' . $config['allow_methods']);
        header('Access-Control-Allow-Headers: ' . $config['allow_headers']);
        header('Access-Control-Expose-Headers: ' . $config['expose_headers']);
        header('Access-Control-Max-Age: ' . (string) $config['max_age']);

        if ($request->method() === 'OPTIONS') {
            return [
                'status' => 204,
                'message' => 'No content',
                'data' => null,
            ];
        }

        return $next($request);
    }
}
