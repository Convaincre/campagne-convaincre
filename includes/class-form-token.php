<?php

declare(strict_types=1);

namespace Convaincre\Connect;

final class FormToken
{
    public static function issue(
        string $secret,
        string $bindingKey,
        int $bindingVersion,
        int $ttl = 7200,
        ?int $now = null
    ): string {
        $issuedAt = $now ?? time();
        $payload = [
            'b' => $bindingKey,
            'v' => $bindingVersion,
            'e' => $issuedAt + max(60, min($ttl, 21600)),
            'j' => bin2hex(random_bytes(12)),
        ];
        $encoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encoded, $secret);
        return $encoded . '.' . $signature;
    }

    public static function verify(string $secret, string $token, ?int $now = null): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || strlen($parts[1]) !== 64) {
            return null;
        }
        [$encoded, $signature] = $parts;
        $expected = hash_hmac('sha256', $encoded, $secret);
        if (! hash_equals($expected, strtolower($signature))) {
            return null;
        }
        try {
            $payload = json_decode(self::base64UrlDecode($encoded), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }
        if (! is_array($payload)
            || ! isset($payload['b'], $payload['v'], $payload['e'])
            || ! is_string($payload['b'])
            || ! is_int($payload['v'])
            || ! is_int($payload['e'])
            || $payload['e'] < ($now ?? time())
        ) {
            return null;
        }
        return $payload;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new \RuntimeException('token_encoding_invalid');
        }
        return $decoded;
    }
}
