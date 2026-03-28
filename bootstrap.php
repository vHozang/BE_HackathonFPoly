<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $base = __DIR__;
        return $path === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
    }
}

if (!function_exists('load_env')) {
    function load_env(string $envFile): void
    {
        if (!is_file($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return $value;
    }
}

load_env(base_path('.env'));
date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Ho_Chi_Minh'));

$debug = filter_var(env('APP_DEBUG', 'true'), FILTER_VALIDATE_BOOLEAN);
error_reporting($debug ? E_ALL : E_ERROR);
ini_set('display_errors', $debug ? '1' : '0');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = base_path('app') . DIRECTORY_SEPARATOR;

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

set_exception_handler(static function (Throwable $exception): void {
    $status = 500;
    $error = 'internal_server_error';
    $message = 'Internal server error';

    if ($exception instanceof App\Core\HttpException) {
        $status = $exception->getStatusCode();
        $error = $exception->getErrorCode();
        $message = $exception->getMessage();
    } elseif ((bool) env('APP_DEBUG', true)) {
        $message = $exception->getMessage();
    }

    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => $error,
        'message' => $message,
    ], JSON_UNESCAPED_UNICODE);
});
