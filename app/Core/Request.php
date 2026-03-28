<?php
declare(strict_types=1);

namespace App\Core;

class Request
{
    private array $jsonBody = [];
    private array $attributes = [];

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $queryParams,
        private readonly array $bodyParams,
        private readonly array $headers
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $headers = function_exists('getallheaders') ? (getallheaders() ?: []) : [];
        $normalizedHeaders = [];
        foreach ($headers as $key => $value) {
            $normalizedHeaders[strtolower($key)] = $value;
        }

        $queryParams = $_GET ?? [];
        $bodyParams = $_POST ?? [];
        $input = file_get_contents('php://input');

        $instance = new self($method, $path, $queryParams, $bodyParams, $normalizedHeaders);

        if ($input !== false && $input !== '') {
            $decoded = json_decode($input, true);
            if (is_array($decoded)) {
                $instance->jsonBody = $decoded;
            } elseif (in_array($method, ['PUT', 'PATCH', 'DELETE'], true)) {
                parse_str($input, $parsed);
                if (is_array($parsed)) {
                    $instance->jsonBody = $parsed;
                }
            }
        }

        return $instance;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->jsonBody)) {
            return $this->jsonBody[$key];
        }
        if (array_key_exists($key, $this->bodyParams)) {
            return $this->bodyParams[$key];
        }
        return $default;
    }

    public function all(): array
    {
        return array_merge($this->bodyParams, $this->jsonBody);
    }

    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = (string) ($this->header('authorization', '') ?? '');
        if (preg_match('/Bearer\s+(.+)/i', $auth, $matches) !== 1) {
            return null;
        }
        return trim($matches[1]);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
