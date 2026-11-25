<?php
declare(strict_types=1);
namespace Convaincre\Connect;

/** Local, optional aggregate counts of server-rendered public pages; no visitor identifiers. */
final class SiteDashboard
{
    public const OPTION = 'convaincre_connect_site_stats';
    public const CRON = 'convaincre_connect_site_stats_cleanup';
    private const LABELS = [
        'home'=>'Accueil','team'=>'Équipe','project'=>'Projet','territory'=>'Territoire',
        'news'=>'Actualités','article'=>'Articles','agenda'=>'Agenda','forms'=>'Formulaires',
        'legal'=>'Mentions légales','privacy'=>'Confidentialité',
    ];
    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'count'], 99);
        add_action('admin_post_cc_site_stats', [self::class, 'settings']);
        add_action(self::CRON, [self::class, 'cleanup']);
    }
    private static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'convaincre_connect_site_stats';
    }
    public static function enabled(): bool
    {
        return get_option(self::OPTION, '') === '1';
    }
    private static function install(): bool
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $table = self::table();
        $charset = $wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            day_key date NOT NULL,
            section_key varchar(24) NOT NULL,
            hits bigint unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY  (day_key,section_key)
        ) {$charset};");
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }
    public static function eligible(?string $section, string $method, bool $loggedIn, bool $protected): bool
    {
        return $section !== null && isset(self::LABELS[$section]) && $method === 'GET' && !$loggedIn && !$protected;
    }
    public static function count(): void
    {
        if (!self::enabled() || is_admin() || is_404() || is_trackback() || is_search()) { return; }
        $section = SiteMode::section();
        $post = get_queried_object();
        if (!$post instanceof \WP_Post || $post->post_status !== 'publish') { return; }
        if (!self::eligible($section, sanitize_key(wp_unslash((string)($_SERVER['REQUEST_METHOD'] ?? ''))) === 'get' ? 'GET' : '', is_user_logged_in(), $post->post_password !== '')) { return; }
        // Respect common opt-out signals without storing them.
        if (sanitize_text_field(wp_unslash((string)($_SERVER['HTTP_DNT'] ?? ''))) === '1' || sanitize_text_field(wp_unslash((string)($_SERVER['HTTP_SEC_GPC'] ?? ''))) === '1') { return; }
        global $wpdb;
        $table = self::table();
        $wpdb->query($wpdb->prepare("INSERT INTO %i (day_key, section_key, hits) VALUES (%s,%s,1) ON DUPLICATE KEY UPDATE hits = hits + 1", $table, gmdate('Y-m-d'), $section));
    }
    public static function cleanup(): void
    {
        if (!self::enabled()) { return; }
        global $wpdb;
        $table = self::table();
        $wpdb->query($wpdb->prepare("DELETE FROM %i WHERE day_key < %s", $table, gmdate('Y-m-d', time()-89*DAY_IN_SECONDS)));
    }
    public static function settings(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.', '', ['response'=>403]); }
        check_admin_referer('cc_site_stats');
        $intent = isset($_POST['intent']) && is_string($_POST['intent']) ? sanitize_key(wp_unslash($_POST['intent'])) : ''; 
        $status = 'error';
        if ($intent === 'enable' && self::install()) {
            update_option(self::OPTION, '1', false);
            if (!wp_next_scheduled(self::CRON)) { wp_schedule_event(time()+3600, 'daily', self::CRON); }
            self::cleanup();
            $status = 'enabled';
        } elseif ($intent === 'disable') {
            delete_option(self::OPTION);
            wp_clear_scheduled_hook(self::CRON);
            global $wpdb;
            $table = self::table();
            $result = $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $table));
            $status = $result === false ? 'error' : 'disabled';
        }
        wp_safe_redirect(add_query_arg(['page'=>'cc-site-dashboard','cc_stats'=>$status], admin_url('admin.php')));
        exit;
    }
    public static function schedule(): void
    {
        if (self::enabled() && !wp_next_scheduled(self::CRON)) { wp_schedule_event(time()+3600, 'daily', self::CRON); }
    }
    private static function tile(string $name, string $value, string $detail): void
    {
        echo '<div class="ccsa-metric"><span>' . esc_html($name) . '</span><strong>' . esc_html($value) . '</strong><small>' . esc_html($detail) . '</small></div>';
    }
    public static function admin(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.', '', ['response'=>403]); }
        $site = SiteMode::data();
        $draft = SiteMode::draftData();
        $configured = Settings::configured();
        $age = $configured ? Manifest::age() : null;
        $status = get_option(Settings::STATUS_OPTION, []);
        $failed = is_array($status) && !empty($status['last_error_code']);
        $counts = Queue::counts();
        $forms = $configured && Manifest::fresh() && !$failed ? SiteMode::formSelection((array)($site['forms'] ?? []), Manifest::forms()) : null;
        echo '<div class="wrap ccsa"><h1>Tableau de bord du site</h1>';
        SiteMode::navigation('cc-site-dashboard');
        $notice = isset($_GET['cc_stats']) && is_string($_GET['cc_stats']) ? sanitize_key(wp_unslash($_GET['cc_stats'])) : '';
        if (in_array($notice, ['enabled','disabled','error'], true)) {
            $messages = ['enabled'=>'Statistiques locales activées.','disabled'=>'Statistiques désactivées et compteurs supprimés.','error'=>'Opération incomplète. Vérifiez les droits de la base WordPress et réessayez.'];
            echo '<div class="notice ' . ($notice === 'error' ? 'notice-error' : 'notice-success') . '"><p>' . esc_html($messages[$notice]) . '</p></div>';
        }
        echo '<section><h2>État du site</h2><div class="ccsa-metrics">';
        self::tile('Site', SiteMode::enabled() ? 'Publié' : 'Non publié', isset($site['draft']) ? 'Modifications en brouillon' : 'Configuration du site');
        self::tile('Connexion', !$configured ? 'À configurer' : ($failed ? 'À vérifier' : ($age === null ? 'Non vérifiée' : ($age <= 300 ? 'Synchronisée' : 'À actualiser'))), $age === null ? 'Aucun catalogue reçu' : 'Dernière synchronisation : ' . human_time_diff(time()-$age,time()));
        self::tile('Formulaires', $forms === null ? 'Non vérifié' : (string)(SiteMode::enabled() ? count($forms) : 0), count((array)($draft['forms'] ?? [])) . ' sélectionnés dans le brouillon');
        self::tile('Transmissions', (string)((int)($counts['pending'] ?? 0)+(int)($counts['sending'] ?? 0)), 'En attente ou en cours');
        self::tile('À vérifier', (string)($counts['blocked'] ?? 0), 'Transmissions bloquées');
        self::tile('Informations obligatoires', (string)(5-count(SiteMode::missingFields($draft))) . '/5', 'Présence des informations, pas leur conformité');
        echo '</div><p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=cc-site')) . '">Gérer mon site</a> <a class="button" href="' . esc_url(admin_url('admin.php?page=cc-site-forms')) . '">Vérifier les formulaires et transmissions</a></p></section>';
        echo '<section><h2>Dernières actualités</h2>';
        $q = new \WP_Query(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>5,'has_password'=>false,'no_found_rows'=>true]);
        if (!$q->posts) { echo '<p>Aucune actualité publiée.</p>'; }
        foreach ($q->posts as $post) {
            echo '<div class="ccsa-form-row"><strong>' . esc_html($post->post_title) . '</strong><span>' . esc_html(get_the_date('', $post)) . '</span><a class="button" href="' . esc_url(get_permalink($post)) . '" target="_blank" rel="noopener">Voir</a></div>';
        }
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=cc-site-news')) . '">Mes actualités</a></p></section>';
        echo '<section><h2>Fréquentation agrégée</h2><p>Mesure locale facultative : uniquement la date UTC, la rubrique générale et le nombre de réponses de page côté serveur. Aucun cookie, identifiant de visiteur, adresse IP, URL complète, référent ou donnée de formulaire enregistré par ce module. Aucun envoi à un tiers.</p>';
        echo '<p>Les compteurs ne représentent pas des visiteurs uniques : les robots et rechargements peuvent être inclus, les pages servies par un cache peuvent être absentes. Vérifiez les informations de confidentialité de votre site avant activation.</p>';
        if (self::enabled()) {
            self::report();
        } else { echo '<p>Mesure désactivée par défaut.</p>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cc_site_stats">';
        wp_nonce_field('cc_site_stats');
        echo self::enabled() ? '<button class="button" name="intent" value="disable">Désactiver et supprimer les compteurs</button>' : '<button class="button button-primary" name="intent" value="enable">Activer les statistiques locales</button>';
        echo '</form></section></div>';
    }
    private static function report(): void
    {
        self::cleanup();
        global $wpdb;
        $table = self::table();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT section_key, SUM(hits) AS total FROM %i WHERE day_key >= %s GROUP BY section_key", $table, gmdate('Y-m-d', time()-29*DAY_IN_SECONDS)), ARRAY_A);
        if (!is_array($rows)) { echo '<p>Compteurs indisponibles.</p>'; return; }
        $total = array_sum(array_map(static fn($r)=>(int)$r['total'], $rows));
        echo '<h3>' . esc_html(number_format_i18n($total)) . ' affichages comptabilisés sur les 30 derniers jours</h3>';
        foreach ($rows as $row) {
            $section = (string)$row['section_key'];
            if (!isset(self::LABELS[$section])) { continue; }
            $hits = (int)$row['total'];
            echo '<div class="ccsa-stat-row"><span>' . esc_html(self::LABELS[$section]) . '</span><progress max="' . esc_attr((string)max(1,$total)) . '" value="' . esc_attr((string)$hits) . '" aria-label="' . esc_attr(self::LABELS[$section]) . '"></progress><strong>' . esc_html(number_format_i18n($hits)) . '</strong></div>';
        }
        echo '<p>Conservation : 90 jours maximum sous réserve du fonctionnement des tâches WordPress ; nettoyage également lors de la consultation du tableau de bord.</p>';
    }
}
