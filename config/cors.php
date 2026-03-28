<?php
declare(strict_types=1);

return [
    'allow_origin' => env('CORS_ALLOW_ORIGIN', '*'),
    'allow_methods' => env('CORS_ALLOW_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS'),
    'allow_headers' => env('CORS_ALLOW_HEADERS', 'Content-Type,Authorization,X-Requested-With'),
    'expose_headers' => env('CORS_EXPOSE_HEADERS', 'Content-Type,Authorization'),
    'max_age' => (int) env('CORS_MAX_AGE', '86400'),
];
