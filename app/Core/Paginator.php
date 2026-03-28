<?php
declare(strict_types=1);

namespace App\Core;

class Paginator
{
    public static function resolve(Request $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 20)));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
        ];
    }

    public static function meta(int $total, int $page, int $perPage): array
    {
        $lastPage = $perPage > 0 ? (int) ceil($total / $perPage) : 1;
        return [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $lastPage,
        ];
    }
}
