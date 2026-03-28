<?php
declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'HRM API'),
    'env' => env('APP_ENV', 'local'),
    'debug' => filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN),
    'timezone' => env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    'base_path' => '/api',
    'jwt' => [
        'secret' => env('JWT_SECRET', 'change-this-secret'),
        'issuer' => env('JWT_ISSUER', 'hrm-system'),
        'ttl' => (int) env('JWT_TTL', '3600'),
    ],
];
