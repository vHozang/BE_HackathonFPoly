<?php
declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $globalMiddlewares = [];
    private string $currentGroupPrefix = '';
    private array $currentGroupMiddlewares = [];

    public function addGlobalMiddleware(string|MiddlewareInterface|array $middleware): void
    {
        $this->globalMiddlewares[] = $middleware;
    }

    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $previousPrefix = $this->currentGroupPrefix;
        $previousMiddlewares = $this->currentGroupMiddlewares;

        $this->currentGroupPrefix .= $prefix;
        $this->currentGroupMiddlewares = array_merge($this->currentGroupMiddlewares, $middlewares);

        $callback($this);

        $this->currentGroupPrefix = $previousPrefix;
        $this->currentGroupMiddlewares = $previousMiddlewares;
    }

    public function add(string $method, string $path, callable|array $handler, array $middlewares = []): void
    {
        $fullPath = $this->normalizePath($this->currentGroupPrefix . $path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $fullPath,
            'handler' => $handler,
            'middlewares' => array_merge($this->currentGroupMiddlewares, $middlewares),
            'pattern' => $this->compilePattern($fullPath),
        ];
    }

    public function dispatch(Request $request): array
    {
        if ($request->method() === 'OPTIONS') {
            return ['status' => 204, 'message' => 'No content', 'data' => null];
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            if (preg_match($route['pattern'], $request->path(), $matches) !== 1) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            $request->setAttribute('route_params', $params);

            $handler = function (Request $req) use ($route, $params): array {
                return $this->invokeHandler($route['handler'], $req, $params);
            };

            $pipeline = array_merge($this->globalMiddlewares, $route['middlewares']);
            return $this->runMiddlewarePipeline($request, $pipeline, $handler);
        }

        throw new HttpException('Route not found', 404, 'route_not_found');
    }

    private function runMiddlewarePipeline(Request $request, array $middlewares, callable $destination): array
    {
        $next = array_reduce(
            array_reverse($middlewares),
            function (callable $nextHandler, string|MiddlewareInterface|array $middleware): callable {
                return function (Request $request) use ($middleware, $nextHandler): array {
                    $instance = $this->resolveMiddleware($middleware);
                    if (!$instance instanceof MiddlewareInterface) {
                        throw new HttpException('Invalid middleware', 500, 'middleware_error');
                    }
                    return $instance->handle($request, $nextHandler);
                };
            },
            $destination
        );

        return $next($request);
    }

    private function resolveMiddleware(string|MiddlewareInterface|array $middleware): MiddlewareInterface
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }

        if (is_string($middleware)) {
            return new $middleware();
        }

        if (is_array($middleware) && $middleware !== []) {
            $class = array_shift($middleware);
            if (!is_string($class)) {
                throw new HttpException('Invalid middleware descriptor', 500, 'middleware_error');
            }
            return new $class(...array_values($middleware));
        }

        throw new HttpException('Invalid middleware definition', 500, 'middleware_error');
    }

    private function invokeHandler(callable|array $handler, Request $request, array $params): array
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            $controller = new $class();
            $refMethod = new \ReflectionMethod($controller, (string) $method);
            if ($refMethod->getNumberOfParameters() <= 1) {
                return $controller->{$method}($request);
            }
            return $controller->{$method}($request, $params);
        }

        $reflection = new \ReflectionFunction(\Closure::fromCallable($handler));
        if ($reflection->getNumberOfParameters() <= 1) {
            return $handler($request);
        }
        return $handler($request, $params);
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '//' ? '/' : $normalized;
    }

    private function compilePattern(string $path): string
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}
