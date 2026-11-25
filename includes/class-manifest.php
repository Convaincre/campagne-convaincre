<?php

declare(strict_types=1);

namespace Convaincre\Connect;

use RuntimeException;

final class Manifest
{
    private const MAX_AGE = 300;

    private static bool $refreshedThisRequest = false;

    public static function refresh(): array
    {
        $manifest = (new ApiClient())->manifest();
        self::validate($manifest);
        $manifestJson = wp_json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($manifestJson)) {
            throw new RuntimeException('manifest_encoding_failed');
        }
        $stored = [
            'fetched_at' => time(),
            'hash' => hash('sha256', $manifestJson),
            'data' => $manifest,
        ];
        update_option(Settings::MANIFEST_OPTION, $stored, false);
        set_transient('convaincre_connect_manifest_cache', $stored, 300);
        update_option(Settings::STATUS_OPTION, [
            'connected_at' => time(),
            'last_error_code' => '',
        ], false);
        Queue::releaseBlocked();
        self::$refreshedThisRequest = true;
        return $stored;
    }

    public static function stored(): ?array
    {
        $cached = get_transient('convaincre_connect_manifest_cache');
        if (is_array($cached)) {
            return $cached;
        }
        $stored = get_option(Settings::MANIFEST_OPTION, null);
        if (! is_array($stored) || ! isset($stored['fetched_at'], $stored['data'])) {
            return null;
        }
        set_transient('convaincre_connect_manifest_cache', $stored, 300);
        return $stored;
    }

    public static function fresh(): bool
    {
        $stored = self::stored();
        return $stored !== null && (time() - (int) $stored['fetched_at']) <= self::MAX_AGE;
    }

    public static function bindingByExternalId(string $externalFormId): ?array
    {
        $binding = self::findBinding('external_form_id', $externalFormId);
        if ($binding !== null) {
            return $binding;
        }

        if (! self::$refreshedThisRequest && self::refreshSafely()) {
            return self::findBinding('external_form_id', $externalFormId, false);
        }
        return null;
    }

    public static function bindingByKey(string $bindingKey): ?array
    {
        $binding = self::findBinding('binding_key', $bindingKey);
        if ($binding !== null) {
            return $binding;
        }

        if (! self::$refreshedThisRequest && self::refreshSafely()) {
            return self::findBinding('binding_key', $bindingKey, false);
        }
        return null;
    }

    public static function agenda(): array
    {
        if (! self::fresh()) {
            self::refreshSafely();
        }
        if (! self::fresh()) {
            return [];
        }
        $stored = self::stored();
        $events = $stored['data']['agenda'] ?? [];
        return is_array($events) ? $events : [];
    }

    public static function forms(): array
    {
        $stored = self::stored();
        $forms = $stored['data']['forms'] ?? [];
        return is_array($forms) ? $forms : [];
    }

    public static function catalog(): array
    {
        $stored = self::stored();
        $catalog = $stored['data']['catalog'] ?? [];
        if (! is_array($catalog)) {
            return [
                'modules' => [],
                'territory_modes' => [],
                'territories' => [],
                'municipalities' => [],
            ];
        }
        return [
            'modules' => is_array($catalog['modules'] ?? null)
                ? $catalog['modules']
                : [],
            'territory_modes' => is_array($catalog['territory_modes'] ?? null)
                ? $catalog['territory_modes']
                : [],
            'territories' => is_array($catalog['territories'] ?? null)
                ? $catalog['territories']
                : [],
            'municipalities' => is_array($catalog['municipalities'] ?? null)
                ? $catalog['municipalities']
                : [],
        ];
    }

    public static function age(): ?int
    {
        $stored = self::stored();
        return $stored === null ? null : max(0, time() - (int) $stored['fetched_at']);
    }

    private static function findBinding(string $field, string $value, bool $refreshStale = true): ?array
    {
        if ($refreshStale && ! self::fresh()) {
            self::refreshSafely();
        }

        foreach (self::forms() as $binding) {
            if (($binding[$field] ?? '') === $value) {
                return $binding;
            }
        }
        return null;
    }

    private static function refreshSafely(): bool
    {
        if (self::$refreshedThisRequest) {
            return false;
        }
        self::$refreshedThisRequest = true;
        try {
            self::refresh();
            return true;
        } catch (\Throwable $exception) {
            $status = get_option(Settings::STATUS_OPTION, []);
            if (! is_array($status)) {
                $status = [];
            }
            $status['last_error_code'] = sanitize_key($exception->getMessage());
            update_option(Settings::STATUS_OPTION, $status, false);
            return false;
        }
    }

    private static function validate(array $manifest): void
    {
        if (isset($manifest['organization_id']) || isset($manifest['territory_id'])) {
            throw new RuntimeException('manifest_routing_boundary_violation');
        }
        if (! isset($manifest['site'], $manifest['forms']) || ! is_array($manifest['forms'])) {
            throw new RuntimeException('manifest_invalid');
        }
        foreach ($manifest['forms'] as $form) {
            if (! is_array($form)
                || ! isset($form['binding_key'], $form['external_form_id'], $form['module'], $form['version'])
                || isset($form['organization_id'])
                || isset($form['territory_id'])
            ) {
                throw new RuntimeException('manifest_form_invalid');
            }
            $mode = (string) ($form['territory_mode'] ?? 'fixed');
            if (! in_array($mode, ['fixed', 'declared_address'], true)) {
                throw new RuntimeException('manifest_form_territory_mode_invalid');
            }
            if ($mode === 'declared_address' && ! is_array($form['municipalities'] ?? null)) {
                throw new RuntimeException('manifest_form_municipalities_invalid');
            }
        }
    }
}
