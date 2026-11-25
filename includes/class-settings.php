<?php

declare(strict_types=1);

namespace Convaincre\Connect;

use InvalidArgumentException;
use RuntimeException;

final class Settings
{
    public const OPTION = 'convaincre_connect_settings';
    public const MANIFEST_OPTION = 'convaincre_connect_manifest';
    public const STATUS_OPTION = 'convaincre_connect_status';

    public static function all(): array
    {
        $value = get_option(self::OPTION, []);
        return is_array($value) ? $value : [];
    }

    public static function configured(): bool
    {
        $settings = self::all();
        return ! empty($settings['api_url'])
            && ! empty($settings['key_id'])
            && ! empty($settings['encrypted_secret']);
    }

    public static function apiUrl(): string
    {
        return (string) (self::all()['api_url'] ?? '');
    }

    public static function keyId(): string
    {
        return (string) (self::all()['key_id'] ?? '');
    }

    public static function secret(): string
    {
        $encrypted = (string) (self::all()['encrypted_secret'] ?? '');
        if ($encrypted === '') {
            throw new RuntimeException('connect_secret_missing');
        }
        return Crypto::decrypt($encrypted);
    }

    public static function origin(): string
    {
        $parts = wp_parse_url(home_url('/'));
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('wordpress_origin_invalid');
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
        if ($scheme !== 'https' && ! $local) {
            throw new RuntimeException('wordpress_https_required');
        }
        $origin = $scheme . '://' . $host;
        if (! empty($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }
        return $origin;
    }

    public static function save(string $apiUrl, string $keyId, ?string $secret): void
    {
        $apiUrl = self::normalizeApiUrl($apiUrl);
        $keyId = trim($keyId);
        if (! preg_match('/^cc_[a-f0-9]{12,80}$/', $keyId)) {
            throw new InvalidArgumentException('key_id_invalid');
        }

        $current = self::all();
        $next = [
            'api_url' => $apiUrl,
            'key_id' => $keyId,
            'encrypted_secret' => (string) ($current['encrypted_secret'] ?? ''),
        ];
        if ($secret !== null && trim($secret) !== '') {
            if (strlen($secret) < 32 || strlen($secret) > 256) {
                throw new InvalidArgumentException('secret_invalid');
            }
            $next['encrypted_secret'] = Crypto::encrypt(trim($secret));
        }
        if ($next['encrypted_secret'] === '') {
            throw new InvalidArgumentException('secret_required');
        }
        update_option(self::OPTION, $next, false);
    }

    public static function clearManifest(): void
    {
        delete_option(self::MANIFEST_OPTION);
        delete_transient('convaincre_connect_manifest_cache');
    }

    private static function normalizeApiUrl(string $value): string
    {
        $value = rtrim(trim($value), '/');
        $parts = wp_parse_url($value);
        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('api_url_invalid');
        }
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower((string) $parts['host']);
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local');
        if ($scheme !== 'https' && ! $local) {
            throw new InvalidArgumentException('https_required');
        }
        $path = (string) ($parts['path'] ?? '');
        if (($path !== '' && $path !== '/')
            || ! empty($parts['query'])
            || ! empty($parts['fragment'])
            || ! empty($parts['user'])
            || ! empty($parts['pass'])
        ) {
            throw new InvalidArgumentException('api_url_invalid');
        }
        return $scheme . '://' . $host . (! empty($parts['port']) ? ':' . (int) $parts['port'] : '');
    }
}
