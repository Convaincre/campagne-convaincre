<?php

declare(strict_types=1);

namespace Convaincre\Connect;

use RuntimeException;

final class Crypto
{
    private const VERSION = 'v1';

    public static function encrypt(string $plaintext): string
    {
        if (! function_exists('openssl_encrypt')) {
            throw new RuntimeException('openssl_unavailable');
        }

        $key = self::key();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::VERSION,
            16
        );

        if ($ciphertext === false) {
            throw new RuntimeException('encryption_failed');
        }

        return self::VERSION . '.' . self::base64UrlEncode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $encoded): string
    {
        if (! str_starts_with($encoded, self::VERSION . '.')) {
            throw new RuntimeException('encrypted_value_version_invalid');
        }

        $raw = self::base64UrlDecode(substr($encoded, 3));
        if (strlen($raw) < 29) {
            throw new RuntimeException('encrypted_value_invalid');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::VERSION
        );

        if ($plaintext === false) {
            throw new RuntimeException('decryption_failed');
        }

        return $plaintext;
    }

    public static function encryptJson(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        return self::encrypt($json);
    }

    public static function decryptJson(string $encoded): array
    {
        $decoded = json_decode(self::decrypt($encoded), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('encrypted_json_invalid');
        }
        return $decoded;
    }

    private static function key(): string
    {
        $material = '';
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT'] as $constant) {
            if (defined($constant)) {
                $material .= (string) constant($constant);
            }
        }
        if ($material === '') {
            throw new RuntimeException('wordpress_secret_material_missing');
        }
        return hash('sha256', 'convaincre-connect|' . $material, true);
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
            throw new RuntimeException('base64_invalid');
        }
        return $decoded;
    }
}
