<?php
declare(strict_types=1);

namespace App\Core;

class Response
{
    public static function send(array $payload): void
    {
        if (($payload['response_type'] ?? null) === 'binary') {
            $status = (int) ($payload['status'] ?? 200);
            http_response_code($status);

            $headers = $payload['headers'] ?? [];
            if (is_array($headers)) {
                foreach ($headers as $name => $value) {
                    header((string) $name . ': ' . (string) $value);
                }
            }

            $body = $payload['body'] ?? '';
            if (is_string($body)) {
                echo $body;
            }
            return;
        }

        $status = (int) ($payload['status'] ?? 200);
        $success = $status >= 200 && $status < 400;
        $message = (string) ($payload['message'] ?? ($success ? 'OK' : 'Request failed'));
        $data = $payload['data'] ?? null;
        $meta = $payload['meta'] ?? null;
        $error = $payload['error'] ?? null;

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        $body = [
            'success' => $success,
            'message' => $message,
            'data' => $data,
        ];

        if ($meta !== null) {
            $body['meta'] = $meta;
        }
        if ($error !== null) {
            $body['error'] = $error;
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE);
    }
}
