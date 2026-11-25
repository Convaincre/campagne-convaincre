<?php
declare(strict_types=1);
namespace Convaincre\Connect;

/** Optional public-site presentation; the existing Connect API remains the only form backend. */
final class SiteMode
{
    public const OPTION = 'convaincre_connect_site_mode';
    private const SECTIONS = [
        'home' => 'Accueil', 'team' => 'Notre équipe', 'project' => 'Notre projet',
        'territory' => 'Notre territoire', 'news' => 'Actualités', 'agenda' => 'Agenda',
        'forms' => 'Nous suivre', 'legal' => 'Mentions légales', 'privacy' => 'Confidentialité',
    ];
    public const SOCIALS = ['facebook'=>'Facebook','instagram'=>'Instagram','x'=>'X','linkedin'=>'LinkedIn','bluesky'=>'Bluesky','youtube'=>'YouTube','tiktok'=>'TikTok','threads'=>'Threads','mastodon'=>'Mastodon'];
    public static function socialIcon(string $key): string
    {
        if (!isset(self::SOCIALS[$key])) { return ''; }
        return '<img class="ccs-social-icon" src="' . esc_url(CONVAINCRE_CONNECT_URL . 'assets/social/' . $key . '.svg') . '" alt="" width="24" height="24" aria-hidden="true">';
    }
    public static function socialUrl(mixed $value): string
    {
        if (!is_string($value)) { return ''; }
        $url = trim($value);
        if ($url === '') { return ''; }
        if (!preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) { $url = 'https://' . ltrim($url, '/'); }
        if (str_starts_with(strtolower($url), 'http://')) { $url = 'https://' . substr($url, 7); }
        if (!filter_var($url, FILTER_VALIDATE_URL) || strtolower((string)wp_parse_url($url, PHP_URL_SCHEME)) !== 'https' || !wp_parse_url($url, PHP_URL_HOST) || wp_parse_url($url, PHP_URL_USER) || wp_parse_url($url, PHP_URL_PASS)) { return ''; }
        return esc_url_raw($url, ['https']);
    }
    public static function socialLinks(array $d): array
    {
        $links = [];
        foreach (self::SOCIALS as $key=>$label) {
            $entry = $d['socials'][$key] ?? [];
            $url = is_array($entry) ? self::socialUrl($entry['url'] ?? '') : '';
            if (!empty($entry['enabled']) && filter_var($url, FILTER_VALIDATE_URL) && strtolower((string)wp_parse_url($url, PHP_URL_SCHEME)) === 'https' && wp_parse_url($url, PHP_URL_HOST) && !wp_parse_url($url, PHP_URL_USER) && !wp_parse_url($url, PHP_URL_PASS)) {
                $links[$key] = ['label'=>$label,'url'=>$url];
            }
        }
        return $links;
    }
    public static function dateValue(mixed $date): string
    {
        if (!is_string($date) || !preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/D', $date, $m)) { return ''; }
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $date : '';
    }
    public static function electionLabel(array $d): string
    {
        $name = trim((string)($d['election_name'] ?? ''));
        $first = self::dateValue($d['election_first'] ?? '');
        $second = self::dateValue($d['election_second'] ?? '');
        if ($name === '' || $first === '') { return ''; }
        $format = static fn($date) => wp_date('j F Y', (new \DateTimeImmutable($date . ' 12:00', wp_timezone()))->getTimestamp());
        return $name . ' · ' . ($second !== '' && $second > $first ? '1er tour : ' . $format($first) . ' · 2d tour : ' . $format($second) : $format($first));
    }
    public const CTA_MODULES = [
        'newsletter'=>['Newsletter','Rester informé'],
        'contact'=>['Contact','Nous contacter'],
        'petition'=>['Pétition','Signer la pétition'],
        'support'=>['Soutien','Découvrir le formulaire de soutien'],
        'volunteering'=>['Bénévolat','Découvrir le formulaire de bénévolat'],
        'event'=>['Événement','Voir les inscriptions'],
        'questionnaire'=>['Questionnaire','Répondre au questionnaire'],
    ];
    public static function contactForm(array $d, array $forms): ?array
    {
        return self::actionForm($d, $forms, 'cta_form', true);
    }
    public static function actionForm(array $d, array $forms, string $key, bool $auto = false): ?array
    {
        if (!empty($d['hidden_sections']['forms'])) { return null; }
        $chosen = $d[$key] ?? ($auto ? 'auto' : '');
        if ($chosen === '') { return null; }
        foreach ($forms as $form) {
            if (!is_array($form) || !isset(self::CTA_MODULES[$form['module'] ?? ''])) { continue; }
            $id = $form['external_form_id'] ?? '';
            if (!is_string($id) || $id === '' || !in_array($id, $d['forms'] ?? [], true)) { continue; }
            if ($chosen === $id || ($auto && $chosen === 'auto' && in_array($form['module'], ['newsletter','contact'], true))) { return $form; }
        }
        return null;
    }
    public static function actionMode(mixed $mode): string
    {
        return is_string($mode) && in_array($mode, ['hero','banner','inline'], true) ? $mode : 'hero';
    }
    private static function actionPicker(string $key, string $label, string $choice, array $forms, bool $auto = false): void
    {
        echo '<label class="ccsa-field"><strong>' . esc_html($label) . '</strong><select name="' . esc_attr($key) . '">';
        if ($auto) { echo '<option value="auto"' . selected($choice, 'auto', false) . '>Automatique : premier contact ou newsletter sélectionné</option>'; }
        echo '<option value=""' . selected($choice, '', false) . '>Aucun</option>';
        $options = [];
        foreach ($forms as $form) {
            if (!is_array($form) || !isset(self::CTA_MODULES[$form['module'] ?? ''])) { continue; }
            $id = (string)($form['external_form_id'] ?? '');
            if ($id === '') { continue; }
            $options[] = $id;
            echo '<option value="' . esc_attr($id) . '"' . selected($choice, $id, false) . '>' . esc_html(self::CTA_MODULES[$form['module']][0] . ' — ' . ($form['name'] ?? $id)) . '</option>';
        }
        if ($choice !== '' && $choice !== 'auto' && !in_array($choice, $options, true)) { echo '<option value="' . esc_attr($choice) . '" selected>Choix enregistré (catalogue indisponible ou formulaire retiré)</option>'; }
        echo '</select></label>';
    }
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('admin_post_cc_site_save', [self::class, 'save']);
        add_action('admin_post_cc_site_disable', [self::class, 'disableAction']);
        add_action('admin_post_cc_site_preview', [self::class, 'preview']);
        add_action('admin_post_cc_site_pair', [self::class, 'pair']);
        add_action('admin_post_cc_site_news_save', [SiteNews::class, 'save']);
        add_action('admin_post_cc_site_news_trash', [SiteNews::class, 'trash']);
        add_action('admin_post_cc_site_refresh', [self::class, 'refreshAction']);
        add_action('admin_post_cc_site_form_selection', [self::class, 'saveFormSelection']);
        add_action('admin_enqueue_scripts', [self::class, 'adminAssets']);
        add_action('admin_init', [self::class, 'welcome']);
        add_filter('template_include', [self::class, 'template'], PHP_INT_MAX);
        add_action('template_redirect', [self::class, 'publicHeaders'], 0);
        add_filter('get_site_icon_url', [self::class, 'siteIcon'], 30, 3);
        // The managed template emits its own stylesheet after wp_head, as in private preview.
        SiteSeo::register();
        SiteDashboard::register();
        SiteDashboard::schedule();
    }
    public static function activation(bool $networkWide = false): void
    {
        if (!$networkWide && !self::enabled()) {
            set_transient('cc_site_welcome', 1, 120);
        }
    }
    public static function welcome(): void
    {
        if (!current_user_can('manage_options') || !get_transient('cc_site_welcome') || wp_doing_ajax()) {
            return;
        }
        delete_transient('cc_site_welcome');
        if (is_network_admin() || isset($_GET['activate-multi']) || (defined('WP_CLI') && WP_CLI)) {
            return;
        }
        wp_safe_redirect(admin_url('admin.php?page=cc-site'));
        exit;
    }
    public static function data(): array
    {
        $v = get_option(self::OPTION, []);
        return is_array($v) ? $v : [];
    }
    public static function draftData(): array
    {
        $d = self::data();
        return is_array($d['draft'] ?? null) ? array_replace($d, $d['draft']) : $d;
    }
    public static function previewUrl(string $section = 'home'): string
    {
        return wp_nonce_url(add_query_arg(['action'=>'cc_site_preview','section'=>$section], admin_url('admin-post.php')), 'cc_site_preview');
    }
    public static function preview(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.', '', ['response'=>403]); }
        check_admin_referer('cc_site_preview');
        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow, noarchive', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Frame-Options: SAMEORIGIN', true);
        $ccPreview = true;
        $requested = isset($_GET['section']) && is_string($_GET['section']) ? sanitize_key($_GET['section']) : 'home';
        $ccPreviewSection = isset(self::SECTIONS[$requested]) ? $requested : 'home';
        require CONVAINCRE_CONNECT_DIR . 'templates/site.php';
        exit;
    }
    /** The code is a transport format for the existing secret, not a new authentication mechanism. */
    public static function parsePairingCode(string $code): array
    {
        if (strlen($code) > 2048 || !str_starts_with(trim($code), 'CC1:')) {
            throw new \InvalidArgumentException('pair_invalid');
        }
        $value = json_decode(substr(trim($code), 4), true);
        if (!is_array($value) || count($value) !== 3) { throw new \InvalidArgumentException('pair_invalid'); }
        foreach (['api_url','key_id','secret'] as $key) {
            if (!isset($value[$key]) || !is_string($value[$key]) || trim($value[$key]) === '') {
                throw new \InvalidArgumentException('pair_invalid');
            }
        }
        return $value;
    }
    public static function pair(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.'); }
        check_admin_referer('cc_site_pair');
        // Never silently rebind an existing site with queued submissions.
        if (Settings::configured()) { self::redirect('pair_existing'); }
        $old = Settings::all();
        try {
            $code = self::parsePairingCode(isset($_POST['pair_code']) && is_string($_POST['pair_code']) ? wp_unslash($_POST['pair_code']) : '');
            Settings::save($code['api_url'], $code['key_id'], $code['secret']);
            Settings::clearManifest();
            Manifest::refresh();
        } catch (\Throwable $e) {
            update_option(Settings::OPTION, $old, false);
            Settings::clearManifest();
            delete_option(Settings::STATUS_OPTION);
            self::redirect('pair_error');
        }
        self::redirect('pair_ok');
    }
    public static function enabled(): bool
    {
        return !empty(self::data()['enabled']);
    }
    public static function menu(): void
    {
        add_submenu_page('convaincre-connect', 'Tableau de bord du site', 'Tableau de bord du site', 'manage_options', 'cc-site-dashboard', [SiteDashboard::class, 'admin']);
        add_submenu_page('convaincre-connect', 'Mon site internet', 'Mon site internet', 'manage_options', 'cc-site', [self::class, 'admin']);
        add_submenu_page('convaincre-connect', 'Mes actualités', 'Mes actualités', 'manage_options', 'cc-site-news', [SiteNews::class, 'admin']);
        add_submenu_page('convaincre-connect', 'Mes formulaires', 'Mes formulaires', 'manage_options', 'cc-site-forms', [self::class, 'formsAdmin']);
    }
    public static function adminAssets(string $hook): void
    {
        if (!in_array($_GET['page'] ?? '', ['cc-site','cc-site-news','cc-site-forms','cc-site-dashboard'], true) || !current_user_can('manage_options')) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('cc-site-admin', CONVAINCRE_CONNECT_URL . 'assets/site-admin.css', [], CONVAINCRE_CONNECT_VERSION);
        wp_enqueue_script('cc-site-admin', CONVAINCRE_CONNECT_URL . 'assets/site-admin.js', ['jquery'], CONVAINCRE_CONNECT_VERSION, true);
    }
    public static function navigation(string $active): void
    {
        echo '<nav class="ccsa-navigation" aria-label="Administration du site">';
        foreach (['cc-site-dashboard'=>'Tableau de bord', 'cc-site'=>'Mon site', 'cc-site-news'=>'Mes actualités', 'cc-site-forms'=>'Mes formulaires'] as $page=>$label) {
            echo '<a class="button ' . ($page === $active ? 'button-primary' : 'button-secondary') . '" href="' . esc_url(admin_url('admin.php?page=' . $page)) . '"' . ($page === $active ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
        }
        if (self::enabled()) {
            echo '<a class="button" href="' . esc_url(home_url('/')) . '" target="_blank" rel="noopener">Voir le site</a>';
        }
        echo '</nav>';
    }
    public static function refreshAction(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.'); }
        check_admin_referer('cc_site_refresh');
        try { Manifest::refresh(); $status = 'ok'; } catch (\Throwable $e) { $status = 'error'; }
        wp_safe_redirect(add_query_arg(['page'=>'cc-site-forms','cc_refresh'=>$status], admin_url('admin.php')));
        exit;
    }
    /** Only identifiers belonging to the current signed catalogue are selectable. */
    public static function formSelection(array $submitted, array $catalog): array
    {
        $allowed = [];
        foreach ($catalog as $form) {
            $id = is_array($form) ? ($form['external_form_id'] ?? null) : null;
            if (is_string($id) && $id !== '') { $allowed[] = $id; }
        }
        return array_values(array_unique(array_intersect(array_filter($submitted, 'is_string'), $allowed)));
    }
    public static function saveFormSelection(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.', '', ['response'=>403]); }
        check_admin_referer('cc_site_form_selection');
        $status = 'error';
        if (Settings::configured() && ($_POST['cc_forms_complete'] ?? '') === '1') {
            try {
                Manifest::refresh();
                $submitted = isset($_POST['forms']) && is_array($_POST['forms']) ? wp_unslash($_POST['forms']) : [];
                $selected = self::formSelection($submitted, Manifest::forms());
                // If a form disappeared since the editor loaded, preserve the draft.
                if (count(array_unique(array_filter($submitted, 'is_string'))) !== count($selected)) {
                    throw new \RuntimeException('catalog_changed');
                }
                $old = self::data();
                $draft = self::draftData();
                $draft['forms'] = $selected;
                $draft['wizard_step'] = 'forms';
                if (!empty($old['enabled'])) {
                    $old['draft'] = array_diff_key($draft, array_flip(['enabled','pages','previous','draft','publication_id']));
                    update_option(self::OPTION, $old, false);
                } else {
                    update_option(self::OPTION, $draft, false);
                }
                $status = 'saved';
            } catch (\Throwable $e) {
                $status = 'error';
            }
        }
        wp_safe_redirect(add_query_arg(['page'=>'cc-site-forms','cc_selection'=>$status], admin_url('admin.php')));
        exit;
    }
    public static function formsAdmin(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.'); }
        echo '<div class="wrap ccsa"><h1>Mes formulaires</h1>';
        self::navigation('cc-site-forms');
        if (!Settings::configured()) {
            echo '<section><h2>Connectez votre site</h2><p>Les formulaires et les réponses sont gérés dans votre espace Convaincre.</p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=cc-site')) . '">Configurer la connexion</a></section></div>';
            return;
        }
        $status = isset($_GET['cc_refresh']) && is_string($_GET['cc_refresh']) ? sanitize_key(wp_unslash($_GET['cc_refresh'])) : '';
        if ($status === 'ok' || $status === 'error') {
            echo '<div class="notice ' . ($status === 'ok' ? 'notice-success' : 'notice-error') . '"><p>' . ($status === 'ok' ? 'Catalogue actualisé.' : 'Actualisation impossible. Vérifiez la connexion.') . '</p></div>';
        }
        $selectionStatus = isset($_GET['cc_selection']) && is_string($_GET['cc_selection']) ? sanitize_key(wp_unslash($_GET['cc_selection'])) : '';
        if ($selectionStatus === 'saved' || $selectionStatus === 'error') {
            echo '<div class="notice ' . ($selectionStatus === 'saved' ? 'notice-success' : 'notice-error') . '"><p>' . ($selectionStatus === 'saved' ? 'Sélection enregistrée dans le brouillon. Publiez le site pour rendre les changements visibles.' : 'Sélection non enregistrée. Le catalogue a peut-être changé ou la connexion est indisponible. Actualisez-le et réessayez.') . '</p></div>';
        }
        echo '<section><h2>Mes formulaires disponibles</h2><p>Créez ou modifiez les formulaires dans Convaincre. Choisissez ici ceux qui doivent figurer dans le site. Les changements restent en brouillon jusqu’à la publication.</p>';
        echo '<p><a class="button button-primary" href="' . esc_url(Settings::apiUrl()) . '" target="_blank" rel="noopener noreferrer">Ouvrir mon espace Convaincre</a> <a class="button" href="' . esc_url(admin_url('admin.php?page=cc-site&step=forms#ccsa-forms')) . '">Choisir les formulaires à afficher</a></p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cc_site_refresh">';
        wp_nonce_field('cc_site_refresh');
        echo '<button class="button">Actualiser le catalogue</button></form>';
        $age = Manifest::age();
        echo '<p class="description">' . esc_html($age === null ? 'Catalogue jamais synchronisé.' : ($age <= 300 ? 'Catalogue synchronisé récemment.' : 'Catalogue ancien : actualisez-le avant de modifier la sélection.')) . '</p>';
        $live = self::data();
        $draft = self::draftData();
        $forms = Manifest::forms();
        if (!$forms) { echo '<p>Aucun formulaire synchronisé pour le moment.</p>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cc_site_form_selection">';
        wp_nonce_field('cc_site_form_selection');
        foreach ($forms as $form) {
            $id = (string)($form['external_form_id'] ?? '');
            if ($id === '') { continue; }
            $published = self::enabled() && in_array($id, $live['forms'] ?? [], true);
            $selected = in_array($id, $draft['forms'] ?? [], true);
            $state = $published ? ($selected ? 'Site publié' : 'Retrait prévu dans le brouillon') : ($selected ? 'Ajout prévu dans le brouillon' : 'Non affiché');
            echo '<div class="ccsa-form-row"><label class="ccsa-form-choice"><input type="checkbox" name="forms[]" value="' . esc_attr($id) . '"' . checked($selected, true, false) . '><span><strong>' . esc_html((string)($form['name'] ?? $id)) . '</strong><br><small>' . esc_html((string)($form['module'] ?? '')) . '</small></span></label><span>' . esc_html($state) . '</span>';
            if ($published && !empty($live['pages']['forms']) && self::owns((int)$live['pages']['forms'], 'forms') && get_post_status((int)$live['pages']['forms']) === 'publish') {
                echo '<a class="button" href="' . esc_url(add_query_arg('cc_form', $id, get_permalink((int)$live['pages']['forms']))) . '" target="_blank" rel="noopener noreferrer">Voir le formulaire public</a>';
            }
            echo '</div>';
        }
        if (Manifest::fresh()) {
            echo '<input type="hidden" name="cc_forms_complete" value="1"><p><button class="button button-primary">Enregistrer la sélection dans le brouillon</button> <a class="button" href="' . esc_url(admin_url('admin.php?page=cc-site&step=publish')) . '">Vérifier et publier le site</a></p>';
        }
        echo '</form>';
        $counts = Queue::counts();
        echo '</section><section><h2>Transmission des réponses</h2><p>Les réponses et les coordonnées ne sont pas affichées ni exportées dans WordPress. Retrouvez-les dans votre espace Convaincre, selon vos droits.</p>';
        echo '<p><strong>' . esc_html((string)($counts['pending'] ?? 0)) . '</strong> en attente · <strong>' . esc_html((string)($counts['blocked'] ?? 0)) . '</strong> à vérifier</p>';
        echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=convaincre-connect')) . '">Vérifier les transmissions</a></section></div>';
    }
    private static function field(string $name, string $label, mixed $value, bool $area = false, bool $required = false): void
    {
        echo '<label class="ccsa-field"><strong>' . esc_html($label) . '</strong>';
        if ($area) {
            echo '<textarea name="' . esc_attr($name) . '" rows="4"' . ($required ? ' required' : '') . '>' . esc_textarea((string)$value) . '</textarea>';
        } else {
            echo '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr((string)$value) . '"' . ($required ? ' required' : '') . '>';
        }
        echo '</label>';
    }
    public static function image(string $name, int $id, string $label): void
    {
        echo '<div class="ccsa-media"><strong>' . esc_html($label) . '</strong><input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string)$id) . '"><div class="ccsa-preview">';
        if ($id) {
            echo wp_get_attachment_image($id, 'thumbnail');
        }
        echo '</div><button type="button" class="button ccsa-pick">Choisir une image</button> <button type="button" class="button ccsa-clear">Retirer</button></div>';
    }
    private static function supporterRow(string $i, array $v): void
    {
        echo '<div class="ccsa-item ccsa-supporter"><div class="ccsa-supporter-order"><button type="button" class="button ccsa-support-up" aria-label="Monter ce soutien">↑</button> <button type="button" class="button ccsa-support-down" aria-label="Descendre ce soutien">↓</button></div>';
        self::field("supporters[$i][name]", 'Nom du parti ou de l’organisation', $v['name'] ?? '');
        self::image("supporters[$i][image]", (int)($v['image'] ?? 0), 'Logo du soutien');
        echo '<button type="button" class="button ccsa-remove">Retirer ce soutien</button></div>';
    }
    public static function supporters(array $d): array
    {
        $list = [];
        foreach (array_slice((array)($d['supporters'] ?? []), 0, 30) as $row) {
            if (!is_array($row) || !is_string($row['name'] ?? null)) { continue; }
            $name = sanitize_text_field($row['name']);
            $image = self::imageId(is_scalar($row['image'] ?? null) ? $row['image'] : 0);
            if ($name !== '' && $image) { $list[] = ['name'=>$name,'image'=>$image]; }
        }
        return $list;
    }
    private static function teamRow(string $i, array $v): void
    {
        echo '<div class="ccsa-item">';
        self::field("team[$i][name]", 'Nom et prénom', $v['name'] ?? '');
        self::field("team[$i][role]", 'Fonction', $v['role'] ?? '');
        self::image("team[$i][image]", (int)($v['image'] ?? 0), 'Portrait');
        self::field("team[$i][bio]", 'Présentation', $v['bio'] ?? '', true);
        echo '<button type="button" class="button ccsa-remove">Retirer cette personne</button></div>';
    }
    private static function projectRow(string $i, array $v): void
    {
        echo '<div class="ccsa-item">';
        self::field("project[$i][title]", 'Titre', $v['title'] ?? '');
        self::field("project[$i][body]", 'Texte', $v['body'] ?? '', true);
        echo '<button type="button" class="button ccsa-remove">Retirer ce thème</button></div>';
    }
    private const STEPS = [
        'identity'=>'Mon identité', 'appearance'=>'Mon apparence', 'team'=>'Mon équipe',
        'project'=>'Mon projet', 'territory'=>'Mon territoire', 'forms'=>'Nous suivre et appels à l’action',
        'legal'=>'Informations légales', 'publish'=>'Prévisualiser et publier',
    ];
    public static function wizardStep(mixed $value): string
    {
        return is_string($value) && isset(self::STEPS[$value]) ? $value : 'identity';
    }
    public static function missingFields(array $d): array
    {
        $required = ['name'=>'Nom de votre organisation', 'territory'=>'Territoire', 'slogan'=>'Slogan', 'legal'=>'Mentions légales', 'privacy'=>'Politique de confidentialité'];
        $missing = array_filter($required, static fn($label, $key) => !is_string($d[$key] ?? null) || trim($d[$key]) === '', ARRAY_FILTER_USE_BOTH);
        if (!empty($d['election_name']) || !empty($d['election_first']) || !empty($d['election_second'])) {
            if (empty($d['election_name'])) { $missing['election_name'] = 'Intitulé du scrutin'; }
            if (self::dateValue($d['election_first'] ?? '') === '') { $missing['election_first'] = 'Date du scrutin'; }
            if (!empty($d['election_second']) && $d['election_second'] <= ($d['election_first'] ?? '')) { $missing['election_second'] = 'Second tour postérieur au premier'; }
        }
        return $missing;
    }
    private static function wizardStart(array $d, string $notice): string
    {
        if (isset($_GET['step']) && is_string($_GET['step'])) { return self::wizardStep(sanitize_key(wp_unslash($_GET['step']))); }
        if (in_array($notice, ['legal_generated','legal_incomplete'], true)) { return 'legal'; }
        if ($notice === 'missing') {
            $missing = self::missingFields($d);
            return array_intersect(['name','territory','slogan','election_name','election_first','election_second'], array_keys($missing)) ? 'identity' : 'legal';
        }
        if ($notice === 'appearance_preview') { return 'appearance'; }
        if ($notice === 'social_invalid') { return 'forms'; }
        if (in_array($notice, ['published_section','legal_review'], true)) { return self::wizardStep($d['wizard_step'] ?? 'publish'); }
        if (in_array($notice, ['preview','confirm','legal_review','published','publish_failed'], true)) { return 'publish'; }
        return self::wizardStep($d['wizard_step'] ?? 'identity');
    }
    private static function wizardNav(string $start): void
    {
        echo '<div class="ccsa-wizard-nav" hidden data-start="' . esc_attr($start) . '" data-published="' . (self::enabled() ? '1' : '0') . '"><p class="ccsa-step-status" aria-live="polite"></p><progress class="ccsa-step-progress" max="8" value="1" aria-label="Progression dans les étapes"></progress><nav aria-label="Étapes de configuration">';
        foreach (self::STEPS as $key=>$label) {
            echo '<button type="button" class="button ccsa-step-link" data-step="' . esc_attr($key) . '">' . esc_html($label) . '</button>';
        }
        echo '</nav></div>';
    }
    public static function admin(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.'); }
        $d = self::draftData();
        $notice = isset($_GET['cc_site_notice']) && is_string($_GET['cc_site_notice']) ? sanitize_key(wp_unslash($_GET['cc_site_notice'])) : '';
        $messages = [
            'preview' => 'Brouillon enregistré. Vérifiez votre site ci-dessous avant publication.',
            'appearance_preview' => 'Apparence enregistrée dans le brouillon. Le site publié reste inchangé.',
            'legal_generated' => 'Projet de texte généré. Relisez-le, complétez-le et vérifiez-le avant publication.',
            'legal_incomplete' => 'Renseignez tous les champs de ce document pour générer un projet de texte. Le texte existant est conservé.',
            'legal_review' => 'Confirmez avoir relu et vérifié les textes légaux avant de publier. Pour les modifications d’une rubrique, cochez la confirmation dans cette rubrique.',
            'pair_ok' => 'Connexion réussie. Vous pouvez sélectionner vos formulaires.',
            'pair_error' => 'Connexion impossible. Vérifiez le code, l’adresse de ce site dans Convaincre et la connexion HTTPS.',
            'pair_existing' => 'Ce site est déjà connecté. Pour modifier la connexion, utilisez les réglages existants.',
            'saved' => 'Brouillon enregistré. Le site public n’a pas été modifié.',
            'published_section' => 'Rubrique publiée. Les autres modifications en brouillon restent privées.',
            'published' => 'Site publié : les réglages publics ont été enregistrés et vérifiés dans WordPress.',
            'publish_failed' => 'La publication n’a pas pu être confirmée. Vérifiez les réglages et réessayez.',
            'social_invalid' => 'Publication interrompue : renseignez une adresse valide pour chaque réseau social coché.', 'disabled' => 'Mode site désactivé. Les contenus sont conservés.',
            'missing' => 'Vérifiez les informations d’identité, les dates du scrutin si renseigné et les informations légales avant de publier.',
            'confirm' => 'Confirmez le remplacement de la page d’accueil actuelle.', 'error' => 'Impossible de créer les pages. Vérifiez les droits WordPress et réessayez.',
            'incomplete' => 'Le formulaire reçu était incomplet. Aucune modification enregistrée. Vérifiez les limites de votre hébergement ou contactez votre administrateur.',
        ];
        echo '<div class="wrap ccsa"><h1>Campagne Convaincre — Mon site internet</h1><p>Renseignez vos contenus. La mise en page, les menus et les pages sont créés automatiquement.</p>';
        self::navigation('cc-site');
        if (isset($messages[$notice])) {
            echo '<div class="notice ' . (in_array($notice, ['saved','published','published_section','disabled','preview','appearance_preview','pair_ok','legal_generated'], true) ? 'notice-success' : 'notice-error') . '"><p>' . esc_html($messages[$notice]) . '</p></div>';
        }
        echo '<div class="ccsa-status"><strong>' . (self::enabled() ? 'Site publié' : 'Site non publié') . '</strong> · ';
        echo Settings::configured() && Manifest::stored() !== null ? 'Connexion configurée' : 'Formulaires non connectés';
        echo ' · <a href="' . esc_url(admin_url('admin.php?page=convaincre-connect')) . '">Configurer ou vérifier la connexion</a>';
        if (self::enabled()) {
            $live = self::data();
            $liveAppearance = SiteAppearance::normalize($live);
            $draftAppearance = SiteAppearance::normalize($d);
            echo ' · <a href="' . esc_url(self::publicCheckUrl($live)) . '" target="_blank" rel="noopener">Voir le site publié (version fraîche)</a>';
            echo '<p><strong>Modèle publié :</strong> ' . esc_html(SiteAppearance::MODELS[$liveAppearance['model']][0]) . ' · <strong>Réseaux publiés :</strong> ' . esc_html(!empty($live['hidden_sections']['forms']) ? 'Page Nous suivre masquée' : (implode(', ', array_column(self::socialLinks($live), 'label')) ?: 'Aucun')) . '</p>';
            if (!empty($live['draft'])) { echo '<p><strong>Modifications en brouillon :</strong> modèle ' . esc_html(SiteAppearance::MODELS[$draftAppearance['model']][0]) . ' · réseaux ' . esc_html(implode(', ', array_column(self::socialLinks($d), 'label')) ?: 'Aucun') . '. Publiez chaque rubrique séparément ou publiez le brouillon complet.</p>'; }
            if (!empty($live['publication_id'])) { echo '<p class="description">Révision publique : ' . esc_html($live['publication_id']) . '</p>'; }
        }
        echo '</div>';
        if (self::enabled() && (string)get_option('blog_public') === '0') {
            echo '<div class="notice notice-warning inline"><p>WordPress demande actuellement aux moteurs de recherche de ne pas indexer ce site. Si vous souhaitez le rendre trouvable, vérifiez <a href="' . esc_url(admin_url('options-reading.php')) . '">Réglages de lecture</a>. Ce réglage n’est jamais modifié automatiquement.</p></div>';
        }
        if (!Settings::configured()) {
            echo '<section><h2>Connecter mon site à Convaincre</h2><p>Dans Convaincre, ouvrez Connect, ajoutez votre site avec cette adresse : <strong>' . esc_html(home_url('/')) . '</strong>, puis copiez le code de connexion. Collez-le ci-dessous.</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cc_site_pair">';
            wp_nonce_field('cc_site_pair');
            echo '<label class="ccsa-field"><strong>Code de connexion privé</strong><input type="password" name="pair_code" maxlength="2048" required autocomplete="off" spellcheck="false" placeholder="CC1:…"></label><button class="button button-primary">Connecter mon site</button></form><p>Ne partagez pas ce code : il contient le secret de connexion.</p></section>';
        }
        if ($notice === 'preview') {
            echo '<section id="ccsa-preview-panel"><h2>Aperçu privé du brouillon</h2><p>Le public ne voit pas ces modifications. Les formulaires ne transmettent aucune réponse dans cet aperçu.</p><div class="ccsa-preview-controls"><button type="button" class="button ccsa-device" data-width="100%" aria-pressed="true">Ordinateur</button> <button type="button" class="button ccsa-device" data-width="390px" aria-pressed="false">Téléphone</button> <a class="button" target="_blank" rel="noopener noreferrer" href="' . esc_url(self::previewUrl()) . '">Ouvrir en grand</a></div><div class="ccsa-frame-wrap"><iframe title="Aperçu privé du site" referrerpolicy="no-referrer" src="' . esc_url(self::previewUrl()) . '"></iframe></div></section>';
        }
        echo '<form id="ccsa-editor" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('cc_site_save');
        $start = self::wizardStart($d, $notice);
        echo '<input type="hidden" name="action" value="cc_site_save"><input type="hidden" name="wizard_step" id="ccsa-wizard-step" value="' . esc_attr($start) . '">';
        self::wizardNav($start);
        echo '<section data-wizard-step="identity"><h2>1. Mon identité</h2><p>Commencez par les trois informations essentielles. Les photos et la présentation sont facultatives.</p>';
        foreach (['name'=>'Nom de votre organisation', 'territory'=>'Territoire', 'slogan'=>'Slogan'] as $k=>$label) {
            self::field($k, $label, $d[$k] ?? '');
        }
        self::field('intro', 'Présentation (facultatif)', $d['intro'] ?? '', true);
        echo '<h3>Élection</h3><p>Renseignez le scrutin et ses dates pour les afficher sur toutes les pages. Laissez vide pour un site sans élection déterminée.</p>';
        self::field('election_name', 'Intitulé du scrutin', $d['election_name'] ?? '');
        foreach (['election_first'=>'Date du premier tour ou scrutin unique','election_second'=>'Date du second tour (facultatif)'] as $key=>$label) {
            echo '<label class="ccsa-field"><strong>' . esc_html($label) . '</strong><input type="date" name="' . esc_attr($key) . '" value="' . esc_attr(self::dateValue($d[$key] ?? '')) . '"></label>';
        }
        self::image('hero', (int)($d['hero'] ?? 0), 'Photographie principale');
        self::image('logo', (int)($d['logo'] ?? 0), 'Logo');
        echo '</section><section data-wizard-step="appearance"><h2>2. Apparence</h2><p>Choisissez un modèle et une couleur. Vous pourrez les modifier à tout moment.</p><div class="ccsa-models">';
        $appearance = SiteAppearance::normalize($d);
        foreach (SiteAppearance::MODELS as $k=>$spec) {
            echo '<label class="ccsa-model"><input type="radio" name="model" value="' . esc_attr($k) . '"' . checked($appearance['model'], $k, false) . '><span class="ccsa-sample ccsa-sample-' . esc_attr($k) . '" aria-hidden="true"><i></i><b></b><em></em></span><strong>' . esc_html($spec[0]) . '</strong><small>' . esc_html($spec[1]) . '</small></label>';
        }
        echo '</div><div class="ccsa-color-grid"><label>Couleur principale <input name="color" type="color" value="' . esc_attr($appearance['color']) . '"></label><label>Couleur secondaire <input name="secondary" type="color" value="' . esc_attr($appearance['secondary']) . '"></label></div>';
        foreach (['font'=>['Typographie',SiteAppearance::FONTS], 'layout'=>['Composition de l’accueil',SiteAppearance::LAYOUTS]] as $key=>$spec) {
            echo '<label class="ccsa-field"><strong>' . esc_html($spec[0]) . '</strong><select name="' . esc_attr($key) . '">';
            foreach ($spec[1] as $value=>$label) {
                echo '<option value="' . esc_attr($value) . '"' . selected($appearance[$key],$value,false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select></label>';
        }
        echo '<h3>Rubriques du site</h3><p>Renommez, masquez ou réordonnez les rubriques. Les contenus sont conservés. Nous suivre reste en dernière position ; l’accueil et les pages légales restent disponibles.</p><ol class="ccsa-nav-order">';
        foreach ($appearance['nav_order'] as $key) {
            echo '<li><input type="hidden" name="nav_order[]" value="' . esc_attr($key) . '"><label class="ccsa-check"><input type="checkbox" name="section_enabled[' . esc_attr($key) . ']" value="1"' . checked(empty($d['hidden_sections'][$key]), true, false) . '> Afficher ' . esc_html(SiteAppearance::NAV[$key]) . '</label><label class="ccsa-field"><span>Nom public</span><input name="section_labels[' . esc_attr($key) . ']" maxlength="80" value="' . esc_attr(self::heading($key, $d)) . '"></label>';
            if ($key !== 'forms') {
                echo '<button type="button" class="button ccsa-order-up" aria-label="' . esc_attr('Monter ' . SiteAppearance::NAV[$key]) . '">↑</button><button type="button" class="button ccsa-order-down" aria-label="' . esc_attr('Descendre ' . SiteAppearance::NAV[$key]) . '">↓</button>';
            }
            echo '</li>';
        }
        echo '</ol><input type="hidden" name="sections_ready" value="1"><h3>Logos de soutien dans le pied de page</h3><p>Facultatif. Ajoutez les logos des partis ou organisations dont le soutien est confirmé et dont vous êtes autorisé à utiliser le logo. Un nom et une image sont nécessaires pour chaque soutien publié.</p><input type="hidden" name="supporters_ready" value="1"><div id="ccsa-supporters" class="ccsa-items">';
        foreach (array_values((array)($d['supporters'] ?? [])) as $i=>$v) { if (is_array($v)) { self::supporterRow((string)$i, $v); } }
        echo '</div><template id="ccsa-supporters-template">'; self::supporterRow('__INDEX__', []);
        echo '</template><button type="button" class="button ccsa-add" data-list="supporters">Ajouter un logo de soutien</button><div id="ccsa-live-appearance"><h3>Aperçu instantané</h3><p>Les couleurs, le modèle et la composition changent ici sans publier. Les textes et images correspondent au dernier brouillon enregistré.</p><div class="ccsa-preview-controls"><button type="button" class="button ccsa-device" data-width="100%" aria-pressed="true">Ordinateur</button> <button type="button" class="button ccsa-device" data-width="390px" aria-pressed="false">Téléphone</button></div><div class="ccsa-frame-wrap"><iframe id="ccsa-appearance-frame" title="Aperçu instantané de l’apparence" loading="lazy" referrerpolicy="no-referrer" src="' . esc_url(self::previewUrl()) . '"></iframe></div></div><p><button type="submit" class="button button-secondary" name="intent" value="appearance_preview">Enregistrer et ouvrir l’aperçu complet</button></p></section>';
        echo '<section data-wizard-step="team"><h2>3. Mon équipe</h2><p>Facultatif : cette rubrique reste masquée si vous ne renseignez personne.</p><p>Ajoutez uniquement les portraits et biographies dont la publication est autorisée.</p><div id="ccsa-team" class="ccsa-items">';
        foreach (array_values($d['team'] ?? []) as $i=>$v) { self::teamRow((string)$i, $v); }
        echo '</div><template id="ccsa-team-template">'; self::teamRow('__INDEX__', []);
        echo '</template><button type="button" class="button ccsa-add" data-list="team">Ajouter une personne</button></section>';
        echo '<section data-wizard-step="project"><h2>4. Mon projet</h2><p>Facultatif : ajoutez vos thèmes progressivement, sans faire de mise en page.</p><div id="ccsa-project" class="ccsa-items">';
        foreach (array_values($d['project'] ?? []) as $i=>$v) { self::projectRow((string)$i, $v); }
        echo '</div><template id="ccsa-project-template">'; self::projectRow('__INDEX__', []);
        echo '</template><button type="button" class="button ccsa-add" data-list="project">Ajouter un thème</button></section>';
        echo '<section data-wizard-step="territory"><h2>5. Mon territoire et mes actualités</h2><p>Facultatif : présentez les lieux et ajoutez une image publique.</p>';
        self::field('territory_text', 'Présentation du territoire', $d['territory_text'] ?? '', true);
        self::image('territory_image', (int)($d['territory_image'] ?? 0), 'Carte ou photographie publique');
        echo '<p>Les actualités se gèrent dans votre espace dédié. Enregistrez d’abord les modifications de cette page.</p><p><a class="button" href="' . esc_url(admin_url('admin.php?page=cc-site-news')) . '">Mes actualités</a></p></section>';
        echo '<section id="ccsa-forms" data-wizard-step="forms"><h2>6. Agenda et formulaires Convaincre</h2><p>Les définitions et réponses restent gérées par Convaincre. Aucun code court à copier. Seuls les formulaires explicitement sélectionnés seront présentés sur ce site.</p>';
        echo '<label class="ccsa-check"><input type="checkbox" name="agenda" value="1"' . checked(!empty($d['agenda']), true, false) . '> Afficher l’agenda public</label>';
        $forms = Settings::configured() && Manifest::fresh() ? Manifest::forms() : [];
        if (Settings::configured() && Manifest::fresh()) { echo '<input type="hidden" name="forms_catalog_ready" value="1">'; }
        else { echo '<p>Catalogue indisponible ou ancien. Votre sélection existante sera conservée. <a href="' . esc_url(admin_url('admin.php?page=cc-site-forms')) . '">Actualiser mes formulaires</a>.</p>'; }
        if (!$forms) { echo '<p>Aucun formulaire disponible. <a href="' . esc_url(admin_url('admin.php?page=convaincre-connect')) . '">Connecter et actualiser les formulaires</a>.</p>'; }
        foreach ($forms as $f) {
            $id = (string)($f['external_form_id'] ?? '');
            if ($id === '') { continue; }
            echo '<label class="ccsa-check"><input type="checkbox" name="forms[]" value="' . esc_attr($id) . '"' . checked(in_array($id, $d['forms'] ?? [], true), true, false) . '> ' . esc_html((string)($f['name'] ?? $id)) . ' <small>(' . esc_html((string)($f['module'] ?? '')) . ')</small></label>';
        }
        echo '<h3>Réseaux sociaux</h3><p>Cochez uniquement les comptes que vous souhaitez afficher et renseignez leur adresse HTTPS complète.</p>';
        foreach (self::SOCIALS as $key=>$label) {
            $entry = $d['socials'][$key] ?? [];
            echo '<div class="ccsa-social-row"><label class="ccsa-check"><input type="checkbox" name="socials[' . esc_attr($key) . '][enabled]" value="1"' . checked(!empty($entry['enabled']), true, false) . '> ' . wp_kses(self::socialIcon($key), ['img'=>['class'=>true,'src'=>true,'alt'=>true,'width'=>true,'height'=>true,'aria-hidden'=>true]]) . ' ' . esc_html($label) . '</label><label class="ccsa-field"><span>Adresse ' . esc_html($label) . '</span><input type="text" inputmode="url" name="socials[' . esc_attr($key) . '][url]" placeholder="https://…" value="' . esc_attr($entry['url'] ?? '') . '"></label></div>';
        }
        echo '<h3>Appels à l’action sur l’accueil</h3><p>Sélectionnez les formulaires à mettre en avant. Ils doivent aussi être cochés dans la liste des formulaires publics. Les textes sont facultatifs ; par défaut le site reprend le nom du formulaire.</p>';
        self::actionPicker('cta_form', 'Action principale', (string)($d['cta_form'] ?? 'auto'), $forms, true);
        self::actionPicker('cta_secondary_form', 'Action secondaire (facultative)', (string)($d['cta_secondary_form'] ?? ''), $forms);
        echo '<label class="ccsa-field"><strong>Présentation de l’action principale</strong><select name="cta_mode">';
        foreach (['hero'=>'Bouton dans le premier écran','banner'=>'Bandeau dédié sous la présentation','inline'=>'Formulaire intégré directement à l’accueil'] as $value=>$label) {
            echo '<option value="' . esc_attr($value) . '"' . selected(self::actionMode($d['cta_mode'] ?? 'hero'), $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        self::field('cta_title', 'Titre du bloc (facultatif)', $d['cta_title'] ?? '');
        self::field('cta_text', 'Texte du bloc (facultatif)', $d['cta_text'] ?? '', true);
        self::field('cta_button', 'Libellé du bouton principal (facultatif)', $d['cta_button'] ?? '');
        self::field('cta_secondary_button', 'Libellé du bouton secondaire (facultatif)', $d['cta_secondary_button'] ?? '');
        echo '</section><section data-wizard-step="legal"><h2>7. Informations obligatoires</h2><p>Renseignez et vérifiez les textes applicables à votre organisation. Ces champs ne constituent pas une validation juridique.</p>';
        echo '<p>Vous pouvez utiliser vos propres textes ou préparer un projet à partir des informations ci-dessous. Aucun texte n’est publié automatiquement. Les informations doivent correspondre aux traitements réels de votre site.</p>';
        foreach (SiteLegal::FIELDS as $kind=>$fields) {
            $label = $kind === 'legal' ? 'Mentions légales' : 'Politique de confidentialité';
            echo '<div class="ccsa-legal-document"><h3>' . esc_html($label) . '</h3><details><summary>Utiliser l’assistant de rédaction</summary>';
            foreach ($fields as $key=>$spec) {
                echo '<div class="ccsa-legal-field">';
                self::field("legal_helper[$kind][$key]", $spec[0], $d['legal_helper'][$kind][$key] ?? '', $spec[2]);
                echo '<p class="description">' . esc_html($spec[1]) . '</p></div>';
            }
            echo '<p>Ce bouton enregistre votre brouillon et remplace uniquement le texte de ce document si tous les champs sont remplis.</p><button type="submit" class="button button-secondary" name="intent" value="generate_' . esc_attr($kind) . '">Générer ou remplacer le projet de texte</button></details>';
            self::field($kind, $label . ' — texte public à vérifier', $d[$kind] ?? '', true);
            echo '</div>';
        }
        echo '<p>Ressource : <a href="https://www.cnil.fr/fr/conformite-rgpd-information-des-personnes-et-transparence" target="_blank" rel="noopener noreferrer">CNIL — informer les personnes</a>. Vérifiez aussi les mentions spécifiques à chacun de vos formulaires et la réglementation applicable à votre situation.</p>';
        echo '</section><section data-wizard-step="publish"><h2>8. Enregistrer ou publier</h2>';
        echo '<div class="ccsa-readiness"><h3>Vérifications avant publication</h3><p class="ccsa-ready-summary" aria-live="polite"></p><ul>';
        foreach (['name'=>'Nom', 'territory'=>'Territoire', 'slogan'=>'Slogan', 'legal'=>'Mentions légales', 'privacy'=>'Confidentialité'] as $key=>$label) {
            echo '<li data-ready-field="' . esc_attr($key) . '">' . esc_html($label) . ' : <span>' . (isset(self::missingFields($d)[$key]) ? 'À renseigner' : 'Renseigné') . '</span></li>';
        }
        echo '</ul><p>Le contrôle vérifie uniquement la présence des informations, pas leur conformité juridique.</p></div>';
        echo '<label class="ccsa-check"><input type="checkbox" name="legal_review" value="1"> J’ai relu les mentions légales et la politique de confidentialité et vérifié qu’elles correspondent à mon site et à mes traitements de données (obligatoire lors de la première publication ou après modification des textes ou des formulaires).</label>';
        if (!self::enabled()) {
            echo '<label class="ccsa-check"><input type="checkbox" name="confirm" value="1"> Je souhaite utiliser la page d’accueil Campagne Convaincre à la place de la page d’accueil actuelle. Le thème et les pages existants sont conservés.</label>';
        }
        echo '<p><button class="button button-secondary" name="intent" value="draft">Enregistrer le brouillon</button> <button class="button button-secondary" name="intent" value="preview">Enregistrer et prévisualiser</button> ';
        echo '<button class="button button-primary" name="intent" value="publish">' . (self::enabled() ? 'Mettre à jour le site' : 'Publier mon site') . '</button></p></section>';
        if (self::enabled()) {
            echo '<div class="ccsa-quick-publish" hidden><div><strong>Publier cette rubrique</strong><p>Les autres rubriques restent inchangées. Le brouillon complet peut toujours être publié depuis la dernière rubrique.</p></div><label class="ccsa-check ccsa-quick-review" hidden><input type="checkbox" name="section_legal_review" value="1"> J’ai vérifié les textes légaux et les traitements de données concernés par ces changements.</label><div class="ccsa-quick-buttons"><button type="submit" class="button" name="intent" value="draft">Enregistrer le brouillon</button><button type="submit" class="button button-primary" name="intent" value="publish_section">Enregistrer et publier cette rubrique</button></div></div>';
        }
        echo '<div class="ccsa-wizard-actions" hidden><button type="button" class="button ccsa-prev">Précédent</button><button type="submit" class="button" name="intent" value="draft">Enregistrer et quitter</button><button type="submit" class="button button-primary ccsa-next" name="intent" value="next">Enregistrer et continuer</button></div><input type="hidden" name="ccsa_complete" value="1"></form>';
        if (self::enabled()) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cc_site_disable">';
            wp_nonce_field('cc_site_disable');
            echo '<button class="button">Désactiver le mode site et restaurer l’accueil précédent</button></form>';
        }
        echo '</div>';
    }
    private static function imageId(mixed $id): int
    {
        $id = absint($id);
        return $id && wp_attachment_is_image($id) ? $id : 0;
    }
    private static function normalize(array $input, array $old): array
    {
        $d = $old;
        foreach (['name','territory','slogan'] as $k) { $d[$k] = sanitize_text_field((string)($input[$k] ?? '')); }
        foreach (['intro','territory_text','legal','privacy'] as $k) { $d[$k] = sanitize_textarea_field((string)($input[$k] ?? '')); }
        $d['legal_helper'] = SiteLegal::clean(is_array($input['legal_helper'] ?? null) ? $input['legal_helper'] : []);
        foreach (['hero','logo','territory_image'] as $k) { $d[$k] = self::imageId($input[$k] ?? 0); }
        $d = array_replace($d, SiteAppearance::normalize($input));
        $d['election_name'] = sanitize_text_field(is_string($input['election_name'] ?? null) ? $input['election_name'] : '');
        $d['election_first'] = self::dateValue($input['election_first'] ?? '');
        $d['election_second'] = self::dateValue($input['election_second'] ?? '');
        if (($input['sections_ready'] ?? '') === '1') {
            $d['hidden_sections'] = [];
            $d['section_labels'] = [];
            foreach (SiteAppearance::NAV as $key=>$default) {
                if (empty($input['section_enabled'][$key])) { $d['hidden_sections'][$key] = true; }
                $label = $input['section_labels'][$key] ?? '';
                if (is_string($label)) {
                    $label = sanitize_text_field($label);
                    if ($label !== '') { $d['section_labels'][$key] = $label; }
                }
            }
        }
        if (($input['supporters_ready'] ?? '') === '1') {
            $d['supporters'] = self::supporters($input);
        } else { $d['supporters'] = $old['draft']['supporters'] ?? $old['supporters'] ?? []; }
        $d['socials'] = [];
        foreach (self::SOCIALS as $key=>$label) {
            $entry = $input['socials'][$key] ?? [];
            if (!is_array($entry)) { continue; }
            $url = self::socialUrl($entry['url'] ?? '');
            $d['socials'][$key] = ['enabled'=>!empty($entry['enabled']),'url'=>$url !== '' ? $url : sanitize_text_field(is_string($entry['url'] ?? null) ? $entry['url'] : '')];
        }
        foreach (['cta_title','cta_button','cta_secondary_button'] as $key) { $d[$key] = sanitize_text_field(is_string($input[$key] ?? null) ? $input[$key] : ''); }
        $d['cta_text'] = sanitize_textarea_field(is_string($input['cta_text'] ?? null) ? $input['cta_text'] : '');
        $choice = $input['cta_form'] ?? 'auto';
        $d['cta_form'] = is_string($choice) ? sanitize_text_field($choice) : 'auto';
        $d['cta_secondary_form'] = is_string($input['cta_secondary_form'] ?? null) ? sanitize_text_field($input['cta_secondary_form']) : '';
        $d['cta_mode'] = self::actionMode($input['cta_mode'] ?? 'hero');
        $d['team'] = [];
        foreach (array_slice((array)($input['team'] ?? []), 0, 100) as $row) {
            if (!is_array($row)) { continue; }
            $name = sanitize_text_field((string)($row['name'] ?? ''));
            if ($name !== '') { $d['team'][] = ['name'=>$name, 'role'=>sanitize_text_field((string)($row['role'] ?? '')), 'bio'=>sanitize_textarea_field((string)($row['bio'] ?? '')), 'image'=>self::imageId($row['image'] ?? 0)]; }
        }
        $d['project'] = [];
        foreach (array_slice((array)($input['project'] ?? []), 0, 100) as $row) {
            if (!is_array($row)) { continue; }
            $title = sanitize_text_field((string)($row['title'] ?? ''));
            if ($title !== '') { $d['project'][] = ['title'=>$title, 'body'=>sanitize_textarea_field((string)($row['body'] ?? ''))]; }
        }
        // Do not erase selections just because the manifest is stale or temporarily unavailable.
        if (Settings::configured() && Manifest::fresh() && ($input['forms_catalog_ready'] ?? '') === '1') {
            $d['forms'] = self::formSelection((array)($input['forms'] ?? []), Manifest::forms());
        } else {
            $d['forms'] = is_array($old['draft']['forms'] ?? null) ? $old['draft']['forms'] : ($old['forms'] ?? []);
        }
        $d['agenda'] = !empty($input['agenda']);
        $d['wizard_step'] = self::wizardStep($input['wizard_step'] ?? null);
        return $d;
    }
    private static function ready(array $d): bool
    {
        return self::missingFields($d) === [];
    }
    private static function redirect(string $notice): never
    {
        wp_safe_redirect(add_query_arg('cc_site_notice', $notice, admin_url('admin.php?page=cc-site')));
        exit;
    }
    private static function owns(int $id, string $section): bool
    {
        return $id > 0 && get_post_type($id) === 'page' && get_post_meta($id, '_cc_site_section', true) === $section;
    }
    private static function pages(array &$d): bool
    {
        foreach (self::SECTIONS as $section=>$title) {
            $id = (int)($d['pages'][$section] ?? 0);
            if (!self::owns($id, $section) || get_post_status($id) === 'trash') {
                $id = wp_insert_post(['post_type'=>'page','post_status'=>'draft','post_title'=>$title,'post_name'=>'cc-' . $section,'meta_input'=>['_cc_site_section'=>$section]], true);
                if (is_wp_error($id) || !$id) { return false; }
                $d['pages'][$section] = $id;
                // Save generated IDs immediately so a retry does not duplicate pages.
                $stored = self::data();
                $stored['pages'] = $d['pages'];
                update_option(self::OPTION, $stored, false);
            }
        }
        return true;
    }
    public static function publicationKeys(string $step): array
    {
        return match ($step) {
            'identity'=>['name','territory','slogan','intro','hero','logo','election_name','election_first','election_second'],
            'appearance'=>['model','color','secondary','font','layout','nav_order','hidden_sections','section_labels','supporters'],
            'team'=>['team'], 'project'=>['project'], 'territory'=>['territory_text','territory_image'],
            'forms'=>['agenda','forms','socials','cta_title','cta_text','cta_button','cta_form','cta_secondary_form','cta_secondary_button','cta_mode'],
            'legal'=>['legal','privacy','legal_helper'], default=>[],
        };
    }
    public static function save(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.'); }
        check_admin_referer('cc_site_save');
        $input = wp_unslash($_POST);
        if (($input['ccsa_complete'] ?? '') !== '1') { self::redirect('incomplete'); }
        $old = self::data();
        $d = self::normalize($input, $old);
        $intent = $input['intent'] ?? '';
        $scoped = $intent === 'publish_section' && !empty($old['enabled']);
        $publish = $intent === 'publish' || $scoped;
        $pending = [];
        if ($scoped) {
            $keys = self::publicationKeys($d['wizard_step']);
            if (!$keys) { self::redirect('incomplete'); }
            $mask = array_flip($keys);
            $pending = array_diff_key(is_array($old['draft'] ?? null) ? $old['draft'] : [], $mask, ['wizard_step'=>true]);
            $d = array_replace($old, array_intersect_key($d, $mask), ['wizard_step'=>$d['wizard_step']]);
            // Existing draft is not part of this publication; preserve it on success or failure.
            $d['draft'] = $pending;
        }
        $legalNotice = null;
        if (in_array($intent, ['generate_legal','generate_privacy'], true)) {
            $kind = $intent === 'generate_legal' ? 'legal' : 'privacy';
            $draft = SiteLegal::draft($kind, $d['legal_helper'][$kind]);
            if ($draft !== null) { $d[$kind] = $draft; }
            $d['wizard_step'] = 'legal';
            $legalNotice = $draft !== null ? 'legal_generated' : 'legal_incomplete';
        }
        if (($input['intent'] ?? '') === 'next') {
            $steps = array_keys(self::STEPS);
            $index = array_search($d['wizard_step'], $steps, true);
            $d['wizard_step'] = $steps[min(count($steps)-1, (int)$index+1)];
        }
        if (($input['intent'] ?? '') === 'preview') { $d['wizard_step'] = 'publish'; }
        if (($input['intent'] ?? '') === 'appearance_preview') { $d['wizard_step'] = 'appearance'; }
        if (!$publish) {
            // A published site has one live configuration; draft edits remain separate.
            if (!empty($old['enabled'])) {
                $old['draft'] = array_diff_key($d, array_flip(['enabled','pages','previous','draft','publication_id']));
                update_option(self::OPTION, $old, false);
            } else { update_option(self::OPTION, $d, false); }
            self::redirect($legalNotice ?? (in_array($intent, ['preview','appearance_preview'], true) ? $intent : 'saved'));
        }
        foreach (self::SOCIALS as $key=>$label) {
            if (!empty($d['socials'][$key]['enabled']) && self::socialUrl($d['socials'][$key]['url'] ?? '') === '') {
                self::preserveDraft($d);
                self::redirect('social_invalid');
            }
        }
        $legalUnchanged = !empty($old['enabled']) && ($old['legal'] ?? null) === ($d['legal'] ?? null) && ($old['privacy'] ?? null) === ($d['privacy'] ?? null) && ($old['forms'] ?? []) === ($d['forms'] ?? []);
        $legalReviewed = !empty($input['legal_review']) || ($scoped && !empty($input['section_legal_review'])) || $legalUnchanged;
        if (!self::ready($d) || !$legalReviewed || (empty($old['enabled']) && empty($input['confirm']))) {
            if (!empty($old['enabled'])) {
                $old['draft'] = array_replace(array_diff_key($d, array_flip(['enabled','pages','previous','draft','publication_id'])), $pending);
                update_option(self::OPTION, $old, false);
            } else { update_option(self::OPTION, $d, false); }
            self::redirect(!self::ready($d) ? 'missing' : (!$legalReviewed ? 'legal_review' : 'confirm'));
        }
        if (!self::pages($d)) {
            self::preserveDraft($d);
            self::redirect('error');
        }
        if (empty($old['enabled'])) {
            $d['previous'] = ['show_on_front'=>get_option('show_on_front'), 'page_on_front'=>(int)get_option('page_on_front')];
        }
        $changed = [];
        foreach ($d['pages'] as $section=>$id) {
            if (!self::owns((int)$id, (string)$section)) { continue; }
            $status = get_post_status((int)$id);
            $target = self::visible((string)$section, $d) ? 'publish' : 'draft';
            $title = self::heading((string)$section, $d);
            $previousTitle = get_post_field('post_title', (int)$id, 'raw');
            if ($status === $target && $previousTitle === $title) { continue; }
            $changed[(int)$id] = ['status'=>$status,'title'=>$previousTitle];
            $result = wp_update_post(['ID'=>(int)$id, 'post_status'=>$target,'post_title'=>$title], true);
            if (is_wp_error($result) || !$result || get_post_status((int)$id) !== $target) {
                // Best-effort restoration of page statuses; retain the submitted draft.
                foreach (array_reverse($changed, true) as $page=>$previous) {
                    if (is_string($previous['status'])) { wp_update_post(['ID'=>$page,'post_status'=>$previous['status'],'post_title'=>$previous['title']], true); }
                }
                self::preserveDraft($d);
                self::redirect('error');
            }
        }
        $d['enabled'] = true;
        $d['publication_id'] = wp_generate_uuid4();
        unset($d['draft']);
        if ($scoped && $pending) { $d['draft'] = $pending; }
        update_option(self::OPTION, $d, false);
        $live = self::data();
        if (($live['publication_id'] ?? '') !== $d['publication_id'] || ($live['model'] ?? '') !== ($d['model'] ?? '') || ($live['socials'] ?? []) !== ($d['socials'] ?? [])) {
            self::preserveDraft($d);
            self::redirect('publish_failed');
        }
        update_option('page_on_front', (int)$d['pages']['home']);
        update_option('show_on_front', 'page');
        if ((int)get_option('page_on_front') !== (int)$d['pages']['home'] || get_option('show_on_front') !== 'page') { self::redirect('publish_failed'); }
        foreach ($d['pages'] as $page) {
            clean_post_cache((int)$page);
            if (function_exists('rocket_clean_post')) { rocket_clean_post((int)$page); }
            do_action('litespeed_purge_post', (int)$page);
        }
        if (function_exists('rocket_clean_home')) { rocket_clean_home(); }
        do_action('litespeed_purge_url', home_url('/'));
        self::redirect($scoped ? 'published_section' : 'published');
    }
    private static function preserveDraft(array $d): void
    {
        $stored = self::data();
        $stored['pages'] = $d['pages'] ?? ($stored['pages'] ?? []);
        $content = array_diff_key($d, array_flip(['enabled','pages','previous','draft','publication_id']));
        if (!empty($stored['enabled'])) {
            $stored['draft'] = array_replace($content, is_array($d['draft'] ?? null) ? $d['draft'] : []);
        } else {
            $stored = array_replace($stored, $content);
        }
        update_option(self::OPTION, $stored, false);
    }
    public static function disableAction(): void
    {
        if (!current_user_can('manage_options')) { wp_die('Accès refusé.'); }
        check_admin_referer('cc_site_disable');
        self::disable();
        self::redirect('disabled');
    }
    public static function disable(): void
    {
        $d = self::data();
        if (empty($d['enabled'])) { return; }
        if ((int)get_option('page_on_front') === (int)($d['pages']['home'] ?? -1)) {
            $previous = $d['previous'] ?? [];
            update_option('page_on_front', (int)($previous['page_on_front'] ?? 0));
            update_option('show_on_front', ($previous['show_on_front'] ?? '') === 'page' ? 'page' : 'posts');
        }
        foreach ($d['pages'] ?? [] as $section=>$id) {
            if (self::owns((int)$id, (string)$section)) { wp_update_post(['ID'=>(int)$id,'post_status'=>'draft']); }
        }
        $d = self::draftData();
        unset($d['draft']);
        $d['enabled'] = false;
        update_option(self::OPTION, $d, false);
    }
    public static function visible(string $section, array $d): bool
    {
        if (isset(self::SECTIONS[$section]) && !in_array($section, ['home','legal','privacy'], true) && !empty($d['hidden_sections'][$section])) { return false; }
        return match ($section) {
            'team' => !empty($d['team']), 'project' => !empty($d['project']),
            'territory' => !empty($d['territory']),
            'agenda' => !empty($d['agenda']) && Settings::configured(),
            'forms' => self::socialLinks($d) !== [] || (!empty($d['forms']) && Settings::configured()),
            default => true,
        };
    }
    public static function section(): ?string
    {
        if (!self::enabled() || is_feed() || is_embed() || is_preview()) { return null; }
        $id = (int)get_queried_object_id();
        if (is_page() && get_post_status($id) === 'publish') {
            $data = self::data();
            foreach ($data['pages'] ?? [] as $section=>$page) {
                if ($id === (int)$page && self::owns($id, (string)$section) && self::visible((string)$section, $data)) { return (string)$section; }
            }
        }
        return is_singular('post') ? 'article' : null;
    }
    public static function publicCheckUrl(array $d): string
    {
        return add_query_arg('cc_revision', (string)($d['publication_id'] ?? 'initial'), home_url('/'));
    }
    public static function publicHeaders(): void
    {
        if (self::section() === null) { return; }
        // Managed pages use a shared settings option; a publish may not change a post's timestamp.
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        nocache_headers();
        if (!headers_sent()) { header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); }
    }
    public static function template(string $template): string
    {
        return self::section() !== null ? CONVAINCRE_CONNECT_DIR . 'templates/site.php' : $template;
    }
    public static function siteIcon(string $url, int $size, int $blogId): string
    {
        if (is_admin() || ($blogId && $blogId !== get_current_blog_id()) || self::section() === null) { return $url; }
        $id = (int)(self::data()['logo'] ?? 0);
        if (!$id || !wp_attachment_is_image($id)) { return $url; }
        return wp_get_attachment_image_url($id, [$size, $size]) ?: $url;
    }
    public static function assets(): void
    {
        if (self::section() !== null) {
            wp_enqueue_style('cc-site', CONVAINCRE_CONNECT_URL . 'assets/site.css', [], CONVAINCRE_CONNECT_VERSION);
        }
    }
    public static function links(array $d, bool $preview = false): array
    {
        $links = [];
        $order = array_merge(['home'], SiteAppearance::order($d['nav_order'] ?? []), ['legal','privacy']);
        foreach ($order as $section) {
            $label = self::heading($section, $d);
            if ($preview) {
                if (self::visible($section, $d)) { $links[$section] = ['label'=>$label,'url'=>self::previewUrl($section)]; }
                continue;
            }
            $id = (int)($d['pages'][$section] ?? 0);
            if (self::owns($id, $section) && get_post_status($id) === 'publish' && self::visible($section, $d)) {
                $links[$section] = ['label'=>$label,'url'=>get_permalink($id)];
            }
        }
        return $links;
    }
    public static function publicForms(array $d): array
    {
        if (!Settings::configured()) { return []; }
        if (!Manifest::fresh()) { try { Manifest::refresh(); } catch (\Throwable $e) { return []; } }
        if (!Manifest::fresh()) { return []; }
        return array_values(array_filter(Manifest::forms(), static fn($f) => is_array($f) && in_array((string)($f['external_form_id'] ?? ''), $d['forms'] ?? [], true)));
    }
    public static function heading(string $section, array $d = []): string
    {
        $label = $d['section_labels'][$section] ?? '';
        return !in_array($section, ['home','legal','privacy','article'], true) && is_string($label) && trim($label) !== '' ? $label : (self::SECTIONS[$section] ?? '');
    }
    public static function ink(string $color): string
    {
        $rgb = sscanf(ltrim($color, '#'), '%02x%02x%02x');
        if (!is_array($rgb) || count($rgb) !== 3) { return '#ffffff'; }
        $lin = static fn($c) => ($c / 255 <= .04045) ? ($c / 255 / 12.92) : (($c / 255 + .055) / 1.055) ** 2.4;
        $l = .2126*$lin($rgb[0]) + .7152*$lin($rgb[1]) + .0722*$lin($rgb[2]);
        return $l > .179 ? '#000000' : '#ffffff';
    }
}
