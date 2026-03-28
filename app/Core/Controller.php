<?php
declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function ok(mixed $data = null, string $message = 'OK', array $meta = []): array
    {
        $response = [
            'status' => 200,
            'message' => $message,
            'data' => $data,
        ];
        if ($meta !== []) {
            $response['meta'] = $meta;
        }
        return $response;
    }

    protected function created(mixed $data = null, string $message = 'Created'): array
    {
        return [
            'status' => 201,
            'message' => $message,
            'data' => $data,
        ];
    }

    protected function noContent(string $message = 'No content'): array
    {
        return [
            'status' => 204,
            'message' => $message,
            'data' => null,
        ];
    }
}
