<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Controller;
use App\Core\Request;

class HealthController extends Controller
{
    public function index(Request $request): array
    {
        return $this->ok([
            'service' => 'HRM API',
            'version' => 'v1',
            'timestamp' => date(DATE_ATOM),
        ], 'Service healthy');
    }
}
