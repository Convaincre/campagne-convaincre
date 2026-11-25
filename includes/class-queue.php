<?php

declare(strict_types=1);

namespace Convaincre\Connect;

final class Queue
{
    public const CRON_HOOK = 'convaincre_connect_process_queue';

    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'convaincre_connect_queue';
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $table = self::table();
        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            external_submission_id varchar(160) NOT NULL,
            binding_key char(36) NOT NULL,
            binding_version int unsigned NOT NULL,
            encrypted_payload longtext NOT NULL,
            payload_hash char(64) NOT NULL,
            status varchar(24) NOT NULL DEFAULT 'pending',
            attempts int unsigned NOT NULL DEFAULT 0,
            next_attempt_at datetime NOT NULL,
            last_error_code varchar(64) NOT NULL DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY external_submission_id (external_submission_id),
            KEY due_items (status, next_attempt_at),
            KEY binding_items (binding_key, status)
        ) {$charset};";
        dbDelta($sql);
    }

    public static function enqueue(array $payload): int
    {
        global $wpdb;
        $now = current_time('mysql', true);
        $json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new \RuntimeException('queue_json_invalid');
        }
        $inserted = $wpdb->insert(self::table(), [
            'external_submission_id' => (string) $payload['external_submission_id'],
            'binding_key' => (string) $payload['binding_key'],
            'binding_version' => (int) $payload['binding_version'],
            'encrypted_payload' => Crypto::encrypt($json),
            'payload_hash' => hash('sha256', $json),
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt_at' => $now,
            'last_error_code' => '',
            'created_at' => $now,
            'updated_at' => $now,
        ], ['%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']);

        if ($inserted === false) {
            $existing = $wpdb->get_row($wpdb->prepare(
                'SELECT id, payload_hash FROM %i WHERE external_submission_id = %s',
                self::table(),
                (string) $payload['external_submission_id']
            ), ARRAY_A);
            if (is_array($existing)) {
                if (! hash_equals((string) $existing['payload_hash'], hash('sha256', $json))) {
                    throw new \RuntimeException('queue_submission_conflict');
                }
                return (int) $existing['id'];
            }
            throw new \RuntimeException('queue_insert_failed');
        }
        return (int) $wpdb->insert_id;
    }

    public static function process(int $limit = 20): array
    {
        if (get_transient('convaincre_connect_queue_lock')) {
            return ['sent' => 0, 'retry' => 0, 'blocked' => 0];
        }
        set_transient('convaincre_connect_queue_lock', '1', 55);
        $result = ['sent' => 0, 'retry' => 0, 'blocked' => 0];
        try {
            global $wpdb;
            $staleBefore = gmdate('Y-m-d H:i:s', time() - 600);
            $wpdb->query($wpdb->prepare(
                "UPDATE %i SET status = 'pending', next_attempt_at = %s, last_error_code = 'stale_worker_recovered' WHERE status = 'sending' AND updated_at < %s",
                self::table(),
                current_time('mysql', true),
                $staleBefore
            ));
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM %i WHERE status = 'pending' AND next_attempt_at <= %s ORDER BY id ASC LIMIT %d",
                self::table(),
                current_time('mysql', true),
                max(1, min($limit, 100))
            ), ARRAY_A);
            foreach ($rows as $row) {
                self::processRow($row, $result);
            }
        } finally {
            delete_transient('convaincre_connect_queue_lock');
        }
        return $result;
    }

    public static function releaseBlocked(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE %i SET status = 'pending', next_attempt_at = %s, last_error_code = '' WHERE status = 'blocked'",
            self::table(),
            current_time('mysql', true)
        ));
    }

    public static function counts(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT status, COUNT(*) AS total FROM %i GROUP BY status', self::table()),
            ARRAY_A
        );
        $counts = ['pending' => 0, 'blocked' => 0, 'errors' => []];
        foreach ($rows as $row) {
            $counts[(string) $row['status']] = (int) $row['total'];
        }

        $errors = $wpdb->get_results(
            $wpdb->prepare("SELECT last_error_code, COUNT(*) AS total FROM %i WHERE status = 'blocked' GROUP BY last_error_code ORDER BY total DESC, last_error_code ASC", self::table()),
            ARRAY_A
        );
        foreach ($errors as $row) {
            $code = sanitize_key((string) ($row['last_error_code'] ?? ''));
            if ($code === '') {
                $code = 'unknown_error';
            }
            $counts['errors'][$code] = (int) $row['total'];
        }
        return $counts;
    }

    private static function processRow(array $row, array &$result): void
    {
        global $wpdb;
        $id = (int) $row['id'];
        $updated = $wpdb->update(
            self::table(),
            ['status' => 'sending', 'updated_at' => current_time('mysql', true)],
            ['id' => $id, 'status' => 'pending'],
            ['%s', '%s'],
            ['%d', '%s']
        );
        if ($updated !== 1) {
            return;
        }
        try {
            $payload = json_decode(Crypto::decrypt((string) $row['encrypted_payload']), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new \RuntimeException('queue_payload_invalid');
            }
            (new ApiClient())->submit($payload);
            $wpdb->delete(self::table(), ['id' => $id], ['%d']);
            $result['sent']++;
        } catch (ApiException $exception) {
            if ($exception->retryable) {
                self::scheduleRetry($row, $exception->safeCode);
                $result['retry']++;
            } else {
                self::block($id, $exception->safeCode);
                $result['blocked']++;
            }
        } catch (\Throwable) {
            self::block($id, 'local_payload_error');
            $result['blocked']++;
        }
    }

    private static function scheduleRetry(array $row, string $code): void
    {
        global $wpdb;
        $attempts = (int) $row['attempts'] + 1;
        $delay = min(21600, 60 * (2 ** min($attempts - 1, 8)));
        $next = gmdate('Y-m-d H:i:s', time() + $delay);
        $wpdb->update(self::table(), [
            'status' => 'pending',
            'attempts' => $attempts,
            'next_attempt_at' => $next,
            'last_error_code' => substr($code, 0, 64),
            'updated_at' => current_time('mysql', true),
        ], ['id' => (int) $row['id']], ['%s', '%d', '%s', '%s', '%s'], ['%d']);
    }

    private static function block(int $id, string $code): void
    {
        global $wpdb;
        $wpdb->update(self::table(), [
            'status' => 'blocked',
            'last_error_code' => substr($code, 0, 64),
            'updated_at' => current_time('mysql', true),
        ], ['id' => $id], ['%s', '%s', '%s'], ['%d']);
    }
}
