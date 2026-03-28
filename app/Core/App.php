<?php
declare(strict_types=1);

namespace App\Core;

use App\Middlewares\CorsMiddleware;

class App
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
        $this->registerMiddlewares();
        $this->registerRoutes();
    }

    public function run(): void
    {
        $request = Request::capture();
        $response = $this->router->dispatch($request);
        Response::send($response);
    }

    private function registerMiddlewares(): void
    {
        $this->router->addGlobalMiddleware(CorsMiddleware::class);
    }

    private function registerRoutes(): void
    {
        $router = $this->router;
        require base_path('routes/api_v1.php');
    }
}
