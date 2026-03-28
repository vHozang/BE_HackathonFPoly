<?php
declare(strict_types=1);

namespace App\Core;

class Jwt
{
    public static function encode(array $payload, string $secret): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];

        $segments = [
            self::base64UrlEncode((string) json_encode($header)),
            self::base64UrlEncode((string) json_encode($payload, JSON_UNESCAPED_UNICODE)),
        ];

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $jwt, string $secret): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new HttpException('Invalid token format', 401, 'invalid_token');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $signingInput = $encodedHeader . '.' . $encodedPayload;
        $expected = self::base64UrlEncode(hash_hmac('sha256', $signingInput, $secret, true));

        if (!hash_equals($expected, $encodedSignature)) {
            throw new HttpException('Invalid token signature', 401, 'invalid_token');
        }

        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);
        if (!is_array($payload)) {
            throw new HttpException('Invalid token payload', 401, 'invalid_token');
        }

        $now = time();
        if (isset($payload['nbf']) && $now < (int) $payload['nbf']) {
            throw new HttpException('Token not active', 401, 'token_not_active');
        }
        if (isset($payload['exp']) && $now >= (int) $payload['exp']) {
            throw new HttpException('Token expired', 401, 'token_expired');
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $padding = strlen($data) % 4;
        if ($padding > 0) {
            $data .= str_repeat('=', 4 - $padding);
        }
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
