<?php

declare(strict_types=1);

namespace Convaincre\Connect;

/**
 * Authenticated inbound synchronization endpoint.
 * This first receiver only stores an idempotent acknowledgement: no public
 * WordPress content is written before publication policy is implemented.
 */
final class SyncReceiver
{
    public static function register(): void
    {
        add_action('rest_api_init', static function (): void {
            register_rest_route('convaincre/v1', '/sync/', [
                'methods' => 'POST',
                'callback' => [self::class, 'receive'],
                'permission_callback' => '__return_true',
            ]);
        });
    }

    public static function cleanup(): void
    {
        global $wpdb;
        // Remove expired replay nonces only; keep delivery receipts for
        // durable idempotency across retries and service restarts.
        $names = $wpdb->get_col($wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT 500",
            $wpdb->esc_like('conv_connect_nonce_') . '%'
        ));
        foreach ($names as $name) {
            $created = (int) get_option($name, 0);
            if ($created > 0 && $created < time() - DAY_IN_SECONDS) {
                delete_option($name);
            }
        }
    }

    public static function receive(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        if (! Settings::configured()) {
            return new \WP_Error('connect_not_configured', 'Connect non configuré.', ['status' => 503]);
        }

        $body = $request->get_body();
        if (strlen($body) > 65536) {
            return new \WP_Error('payload_too_large', 'Charge utile trop importante.', ['status' => 413]);
        }
        $key = (string) $request->get_header('x-convaincre-key');
        $timestamp = (string) $request->get_header('x-convaincre-timestamp');
        $nonce = (string) $request->get_header('x-convaincre-nonce');
        $origin = (string) $request->get_header('x-convaincre-origin');
        $signature = strtolower((string) $request->get_header('x-convaincre-signature'));
        if ($key !== Settings::keyId() || ! ctype_digit($timestamp)
            || abs(time() - (int) $timestamp) > 300
            || ! preg_match('/^[a-f0-9]{32,120}$/', $nonce)
            || ! preg_match('/^[a-f0-9]{64}$/', $signature)
            || ! hash_equals(Settings::origin(), rtrim($origin, '/'))
        ) {
            return new \WP_Error('connect_auth_invalid', 'Authentification invalide.', ['status' => 401]);
        }

        $expected = Signer::signature(
            Settings::secret(), $timestamp, $nonce, $origin,
            'POST', '/wp-json/convaincre/v1/sync/', $body
        );
        if (! hash_equals($expected, $signature)) {
            return new \WP_Error('connect_auth_invalid', 'Authentification invalide.', ['status' => 401]);
        }

        // Nonces are persisted atomically using the unique option_name index.
        // A retry must use a new nonce but retain its delivery idempotency key.
        $nonceOption = 'conv_connect_nonce_' . hash('sha256', $key . ':' . $nonce);
        if (! add_option($nonceOption, time(), '', false)) {
            return new \WP_Error('connect_replay', 'Requête déjà utilisée.', ['status' => 409]);
        }

        $data = json_decode($body, true);
        if (! is_array($data)
            || ! isset($data['delivery_id'], $data['kind'], $data['revision'], $data['content_hash'])
            || ! is_string($data['delivery_id'])
            || ! preg_match('/^[a-f0-9-]{36}$/', $data['delivery_id'])
            || ! in_array($data['kind'], ['event', 'article', 'program_page'], true)
            || ! is_int($data['revision']) || $data['revision'] < 1
            || ! is_string($data['content_hash']) || ! preg_match('/^[a-f0-9]{64}$/', $data['content_hash'])
        ) {
            return new \WP_Error('connect_payload_invalid', 'Charge utile invalide.', ['status' => 400]);
        }

        $public = $data['public_payload'] ?? null;
        $allowed = ['title', 'body', 'excerpt', 'slug', 'starts_at', 'ends_at', 'location', 'published_at', 'status'];
        if (! is_array($public)) {
            return new \WP_Error('connect_public_payload_invalid', 'Projection publique invalide.', ['status' => 400]);
        }
        foreach ($public as $field => $value) {
            if (! in_array($field, $allowed, true) || ! is_string($value)) {
                return new \WP_Error('connect_public_payload_invalid', 'Champ public non autorisé.', ['status' => 400]);
            }
        }
        // Only metadata is acknowledged. Content publication requires a
        // separate explicit editorial workflow and mapping.
        $deliveryOption = 'conv_connect_delivery_' . hash('sha256', $key . ':' . $data['delivery_id']);
        $record = [
            'kind' => $data['kind'],
            'revision' => $data['revision'],
            'content_hash' => $data['content_hash'],
        ];
        if (! add_option($deliveryOption, $record, '', false)) {
            $existing = get_option($deliveryOption);
            if ($existing !== $record) {
                return new \WP_Error('connect_delivery_conflict', 'Livraison contradictoire.', ['status' => 409]);
            }
            return new \WP_REST_Response(['accepted' => true, 'idempotent_replay' => true], 200);
        }
        return new \WP_REST_Response(['accepted' => true, 'idempotent_replay' => false], 202);
    }
}
