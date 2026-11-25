<?php

declare(strict_types=1);

namespace Convaincre\Connect;

final class Plugin
{
    public static function boot(): void
    {
        Form::register();
        Agenda::register();
        Blocks::register();
        SyncReceiver::register();
        add_action('convaincre_connect_cleanup_nonces', [SyncReceiver::class, 'cleanup']);
        if (! wp_next_scheduled('convaincre_connect_cleanup_nonces')) {
            wp_schedule_event(time() + 3600, 'hourly', 'convaincre_connect_cleanup_nonces');
        }
        Admin::register();
        add_action('admin_init', [self::class, 'privacyPolicy']);
        SiteMode::register();
        add_filter('cron_schedules', [self::class, 'cronSchedules']);
        add_action(Queue::CRON_HOOK, [Queue::class, 'process']);
        add_action('convaincre_connect_refresh_manifest', [self::class, 'refreshManifest']);
        if (! wp_next_scheduled(Queue::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'convaincre_connect_five_minutes', Queue::CRON_HOOK);
        }
        if (! wp_next_scheduled('convaincre_connect_refresh_manifest')) {
            wp_schedule_event(time() + 300, 'hourly', 'convaincre_connect_refresh_manifest');
        }
    }

    public static function privacyPolicy(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) { return; }
        wp_add_privacy_policy_content('Campagne Convaincre',
            '<p>Lorsque les formulaires Convaincre sont activés, les données que vous saisissez et les informations techniques nécessaires à leur transmission sont envoyées au service Convaincre configuré par le site. Les soumissions non transmises peuvent être conservées dans une file locale chiffrée. Un compteur temporaire local fondé sur un dérivé de l’adresse IP limite les abus.</p>'
            . '<p>Les statistiques locales, si activées, comptent les affichages par jour sans identifiant de visiteur. Les liens de réseaux sociaux sont des liens simples vers des sites externes.</p>'
            . '<p>À compléter par le responsable du site : destinataires, instance Convaincre, finalités, bases juridiques, durées de conservation, modalités d’exercice des droits et coordonnées de contact.</p>');
    }

    public static function activate(bool $networkWide = false): void
    {
        if (is_multisite() && $networkWide) {
            foreach (get_sites(['fields' => 'ids']) as $siteId) {
                switch_to_blog((int) $siteId);
                self::activateSite();
                restore_current_blog();
            }
            return;
        }
        self::activateSite();
    }

    public static function deactivate(bool $networkWide = false): void
    {
        if (is_multisite() && $networkWide) {
            foreach (get_sites(['fields' => 'ids']) as $siteId) {
                switch_to_blog((int) $siteId);
                SiteMode::disable();
                self::clearSchedules();
                restore_current_blog();
            }
            return;
        }
        SiteMode::disable();
        self::clearSchedules();
    }

    public static function cronSchedules(array $schedules): array
    {
        $schedules['convaincre_connect_five_minutes'] = [
            'interval' => 300,
            'display' => __('Toutes les cinq minutes', 'campagne-convaincre'),
        ];
        return $schedules;
    }

    public static function refreshManifest(): void
    {
        if (! Settings::configured()) {
            return;
        }
        try {
            Manifest::refresh();
        } catch (\Throwable $exception) {
            update_option(Settings::STATUS_OPTION, [
                'last_error_code' => sanitize_key($exception->getMessage()),
                'failed_at' => time(),
            ], false);
        }
    }

    private static function activateSite(): void
    {
        add_filter('cron_schedules', [self::class, 'cronSchedules']);
        Queue::install();
        self::clearSchedules();
        wp_schedule_event(time() + 60, 'convaincre_connect_five_minutes', Queue::CRON_HOOK);
        wp_schedule_event(time() + 300, 'hourly', 'convaincre_connect_refresh_manifest');
    }

    private static function clearSchedules(): void
    {
        wp_clear_scheduled_hook(Queue::CRON_HOOK);
        wp_clear_scheduled_hook('convaincre_connect_refresh_manifest');
        wp_clear_scheduled_hook('convaincre_connect_cleanup_nonces');
        wp_clear_scheduled_hook(SiteDashboard::CRON);
    }
}
