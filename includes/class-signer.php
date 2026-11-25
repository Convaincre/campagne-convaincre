<?php

declare(strict_types=1);

namespace Convaincre\Connect;

final class Signer
{
    public static function canonical(
        string $timestamp,
        string $nonce,
        string $origin,
        string $method,
        string $path,
        string $body,
        string $pluginVersion = ''
    ): string {
        $values = [
            $timestamp,
            $nonce,
            rtrim($origin, '/'),
            strtoupper($method),
            $path,
            hash('sha256', $body),
        ];
        if ($pluginVersion !== '') {
            $values[] = $pluginVersion;
        }
        return implode("\n", $values);
    }

    public static function signature(
        string $secret,
        string $timestamp,
        string $nonce,
        string $origin,
        string $method,
        string $path,
        string $body,
        string $pluginVersion = ''
    ): string {
        return hash_hmac(
            'sha256',
            self::canonical(
                $timestamp,
                $nonce,
                $origin,
                $method,
                $path,
                $body,
                $pluginVersion
            ),
            $secret
        );
    }

    public static function headers(
        string $keyId,
        string $secret,
        string $origin,
        string $method,
        string $path,
        string $body,
        string $pluginVersion = '',
        ?int $timestamp = null,
        ?string $nonce = null
    ): array {
        $timestampValue = (string) ($timestamp ?? time());
        $nonceValue = $nonce ?? bin2hex(random_bytes(24));
        $headers = [
            'X-Convaincre-Key' => $keyId,
            'X-Convaincre-Timestamp' => $timestampValue,
            'X-Convaincre-Nonce' => $nonceValue,
            'X-Convaincre-Origin' => rtrim($origin, '/'),
            'X-Convaincre-Signature' => self::signature(
                $secret,
                $timestampValue,
                $nonceValue,
                $origin,
                $method,
                $path,
                $body,
                $pluginVersion
            ),
        ];
        if ($pluginVersion !== '') {
            $headers['X-Convaincre-Plugin-Version'] = $pluginVersion;
        }
        return $headers;
    }
}
