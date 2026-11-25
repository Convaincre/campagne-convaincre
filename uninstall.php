<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$campagne_convaincre_cleanup = static function (): void {
    global $wpdb;
    $table = $wpdb->prefix . 'convaincre_connect_queue';
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
    $statsTable = $wpdb->prefix . 'convaincre_connect_site_stats';
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $statsTable));
    wp_clear_scheduled_hook('convaincre_connect_site_stats_cleanup');
    foreach ([
        'convaincre_connect_settings',
        'convaincre_connect_appearance',
        'convaincre_connect_manifest',
        'convaincre_connect_status',
        'convaincre_connect_site_stats',
    ] as $option) {
        delete_option($option);
    }
    delete_transient('convaincre_connect_manifest_cache');
    delete_transient('convaincre_connect_queue_lock');
    wp_clear_scheduled_hook('convaincre_connect_process_queue');
    wp_clear_scheduled_hook('convaincre_connect_refresh_manifest');
    wp_clear_scheduled_hook('convaincre_connect_cleanup_nonces');
};

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids']) as $campagne_convaincre_site_id) {
        switch_to_blog((int) $campagne_convaincre_site_id);
        $campagne_convaincre_cleanup();
        restore_current_blog();
    }
} else {
    $campagne_convaincre_cleanup();
}
