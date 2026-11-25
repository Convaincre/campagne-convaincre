<?php

declare(strict_types=1);

namespace Convaincre\Connect;

final class Admin
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_convaincre_connect_save', [self::class, 'save']);
        add_action('admin_post_convaincre_connect_sync', [self::class, 'sync']);
        add_action('admin_post_convaincre_connect_retry', [self::class, 'retry']);
        add_action('admin_post_convaincre_connect_appearance', [self::class, 'saveAppearance']);
    }

    public static function menu(): void
    {
        add_menu_page(
            __('Campagne Convaincre', 'campagne-convaincre'),
            __('Campagne Convaincre', 'campagne-convaincre'),
            'manage_options',
            'convaincre-connect',
            [self::class, 'page'],
            'dashicons-admin-links',
            58
        );
        add_submenu_page('convaincre-connect', 'Apparence', 'Apparence', 'manage_options', 'convaincre-connect-appearance', [self::class, 'appearancePage']);
    }

    public static function page(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'campagne-convaincre'));
        }

        $settings = Settings::all();
        $counts = Queue::counts();
        $age = Manifest::age();
        try {
            $origin = Settings::origin();
        } catch (\Throwable $exception) {
            $origin = __('Indisponible : HTTPS requis', 'campagne-convaincre');
        }
        $notice = isset($_GET['cc_notice']) ? sanitize_key(wp_unslash((string) $_GET['cc_notice'])) : '';

        echo '<div class="wrap"><h1>' . esc_html__('Campagne Convaincre', 'campagne-convaincre') . '</h1>';
        echo '<p>' . esc_html__('Les formulaires se créent et se modifient dans Convaincre. WordPress sert uniquement à les afficher et à transmettre les réponses vers le bon espace.', 'campagne-convaincre') . '</p>';

        if ($notice !== '') {
            $messages = [
                'connection_saved' => __('Connexion enregistrée et formulaires actualisés.', 'campagne-convaincre'),
                'manifest_synced' => __('Formulaires actualisés depuis Convaincre.', 'campagne-convaincre'),
                'queue_retried' => __('Les envois bloqués ont été relancés.', 'campagne-convaincre'),
            ];
            echo '<div class="notice notice-info"><p>'
                . esc_html($messages[$notice] ?? $notice)
                . '</p></div>';
        }

        echo '<h2>' . esc_html__('Connexion', 'campagne-convaincre') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('convaincre_connect_save');
        echo '<input type="hidden" name="action" value="convaincre_connect_save">';
        echo '<table class="form-table"><tbody>';
        self::row('URL Convaincre', '<input class="regular-text" type="url" name="api_url" required value="' . esc_attr((string) ($settings['api_url'] ?? '')) . '">');
        self::row('Key ID', '<input class="regular-text" type="text" name="key_id" required value="' . esc_attr((string) ($settings['key_id'] ?? '')) . '">');
        self::row('Secret', '<input class="regular-text" type="password" name="secret" autocomplete="new-password" placeholder="' . esc_attr__('Laisser vide pour conserver le secret actuel', 'campagne-convaincre') . '">');
        self::row('Origine signée', '<code>' . esc_html($origin) . '</code>');
        echo '</tbody></table>';
        submit_button(__('Enregistrer et tester', 'campagne-convaincre'));
        echo '</form>';

        echo '<hr>';
        echo '<h2>' . esc_html__('Formulaires Convaincre', 'campagne-convaincre') . '</h2>';
        echo '<p>' . esc_html__('Le plugin récupère automatiquement la dernière version d’un formulaire lorsqu’une page WordPress l’affiche. Si vous venez de créer ou modifier un formulaire et souhaitez forcer la mise à jour immédiatement, utilisez le bouton ci-dessous.', 'campagne-convaincre') . '</p>';
        echo '<p><strong>' . esc_html(sprintf(
            /* translators: %s: elapsed time since last synchronization. */
            __('Dernière actualisation : %s', 'campagne-convaincre'),
            $age === null ? __('jamais', 'campagne-convaincre') : self::duration($age)
        )) . '</strong></p>';
        self::actionForm(
            'convaincre_connect_sync',
            'convaincre_connect_sync',
            __('Actualiser les formulaires maintenant', 'campagne-convaincre')
        );

        $forms = Manifest::forms();
        if ($forms === []) {
            echo '<p class="description">' . esc_html__('Aucun formulaire n’est encore synchronisé. Créez-le dans Convaincre puis actualisez cette page.', 'campagne-convaincre') . '</p>';
        } else {
            echo '<table class="widefat striped" style="margin-top:16px"><thead><tr><th>Nom</th><th>Type</th><th>Territoire</th><th>Code court</th></tr></thead><tbody>';
            foreach ($forms as $form) {
                $territory = is_array($form['territory'] ?? null) ? $form['territory'] : [];
                $territoryLabel = (string) ($territory['name'] ?? '');
                if ((string) ($form['territory_mode'] ?? 'fixed') === 'declared_address') {
                    $municipalities = is_array($form['municipalities'] ?? null) ? $form['municipalities'] : [];
                    if (count($municipalities) === 1) {
                        $territoryLabel = (string) ($municipalities[0]['name'] ?? __('Commune configurée dans Convaincre', 'campagne-convaincre'));
                    } else {
                        $territoryLabel = sprintf(
                            /* translators: %d: number of municipalities available. */
                            __('Choix parmi %d communes', 'campagne-convaincre'),
                            count($municipalities)
                        );
                    }
                }
                $shortcode = Shortcodes::forBinding($form);
                echo '<tr><td>' . esc_html((string) ($form['name'] ?? '')) . '</td><td>'
                    . esc_html(self::moduleLabel((string) ($form['module'] ?? ''))) . '</td><td>'
                    . esc_html($territoryLabel) . '</td><td><code>'
                    . esc_html($shortcode) . '</code></td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '<hr>';
        echo '<h2>' . esc_html__('État technique', 'campagne-convaincre') . '</h2>';
        echo '<p>' . esc_html(sprintf(
            /* translators: %1$d: pending submissions; %2$d: blocked submissions. */
            __('File en attente : %1$d, bloquées : %2$d', 'campagne-convaincre'),
            $counts['pending'] ?? 0,
            $counts['blocked'] ?? 0
        )) . '</p>';

        $errors = is_array($counts['errors'] ?? null) ? $counts['errors'] : [];
        if (($counts['blocked'] ?? 0) > 0 && $errors !== []) {
            echo '<div class="notice notice-warning inline"><p><strong>'
                . esc_html__('Pourquoi certains envois sont bloqués :', 'campagne-convaincre')
                . '</strong></p><ul style="list-style:disc;margin-left:1.4em">';
            foreach ($errors as $code => $total) {
                echo '<li>' . esc_html(sprintf(
                    '%d × %s',
                    (int) $total,
                    self::errorLabel((string) $code)
                )) . '</li>';
            }
            echo '</ul><p>'
                . esc_html__('Corrigez la cause indiquée puis utilisez « Relancer les envois bloqués ». Les réponses restent conservées localement tant qu’elles ne sont pas transmises.', 'campagne-convaincre')
                . '</p></div>';
        }

        self::actionForm(
            'convaincre_connect_retry',
            'convaincre_connect_retry',
            __('Relancer les envois bloqués', 'campagne-convaincre')
        );
        echo '</div>';
    }

    public static function appearancePage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'campagne-convaincre'));
        }
        $forms = Manifest::forms();
        $stored = Appearance::stored();
        $formId = isset($_GET['form_id']) && is_string($_GET['form_id']) ? sanitize_text_field(wp_unslash($_GET['form_id'])) : '';
        $known = array_column($forms, 'external_form_id');
        if ($formId !== '' && ! in_array($formId, $known, true)) {
            $formId = '';
        }
        $raw = $formId === '' ? ($stored['site'] ?? []) : ($stored['forms'][$formId] ?? []);
        $style = Appearance::normalize(is_array($raw) ? $raw : [], $formId !== '');
        $effective = Appearance::resolve($stored, $formId);
        $css = Appearance::css($effective);

        wp_enqueue_style('convaincre-connect-forms', CONVAINCRE_CONNECT_URL . 'assets/forms.css', [], CONVAINCRE_CONNECT_VERSION);
        echo '<div class="wrap"><h1>Apparence de Campagne Convaincre</h1>';
        if (isset($_GET['cc_saved']) && $_GET['cc_saved'] === '1') {
            echo '<div class="notice notice-success"><p>Apparence enregistrée.</p></div>';
        }
        echo '<p>Définissez la charte générale du site, ou une exception pour un formulaire. Aucun changement de shortcode nécessaire.</p>';
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '">';
        echo '<input type="hidden" name="page" value="convaincre-connect-appearance"><label>Configuration : <select name="form_id">';
        echo '<option value="">Tous les formulaires du site</option>';
        foreach ($forms as $form) {
            $id = (string) ($form['external_form_id'] ?? '');
            echo '<option value="' . esc_attr($id) . '"' . selected($formId, $id, false) . '>' . esc_html((string) ($form['name'] ?? $id)) . '</option>';
        }
        echo '</select></label> ';
        submit_button('Choisir', 'secondary', 'submit', false);
        echo '</form><hr><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('convaincre_connect_appearance');
        echo '<input type="hidden" name="action" value="convaincre_connect_appearance"><input type="hidden" name="form_id" value="' . esc_attr($formId) . '">';
        $modes = ['theme' => 'Hériter du thème WordPress', 'convaincre' => 'Charte Convaincre', 'custom' => 'Personnaliser'];
        if ($formId !== '') {
            $modes = ['inherit' => 'Hériter des réglages généraux du plugin'] + $modes;
        }
        echo '<table class="form-table"><tbody>';
        $select = '<select name="appearance[mode]">';
        foreach ($modes as $key => $label) {
            $select .= '<option value="' . esc_attr($key) . '"' . selected($style['mode'], $key, false) . '>' . esc_html($label) . '</option>';
        }
        self::row('Mode graphique', $select . '</select>');
        foreach (['primary' => 'Bouton et accent', 'background' => 'Fond du formulaire', 'text' => 'Texte', 'field' => 'Fond des champs', 'border' => 'Bordures'] as $key => $label) {
            if ($key === 'background') {
                self::row($label, '<input name="appearance[background]" value="' . esc_attr($style[$key]) . '" pattern="#[0-9a-fA-F]{6}|transparent" required> <span class="description">#RRGGBB ou transparent</span>');
            } else {
                self::row($label, '<input type="color" name="appearance[' . esc_attr($key) . ']" value="' . esc_attr($style[$key]) . '">');
            }
        }
        foreach (['radius' => ['Arrondis (px)', 0, 32], 'spacing' => ['Espacement (px)', 8, 40], 'width' => ['Largeur maximale (px)', 280, 1200], 'size' => ['Taille du texte (px)', 14, 24]] as $key => [$label, $min, $max]) {
            self::row($label, '<input type="number" name="appearance[' . esc_attr($key) . ']" min="' . $min . '" max="' . $max . '" value="' . esc_attr($style[$key]) . '" required>');
        }
        $fonts = '<select name="appearance[font]">';
        foreach (['inherit' => 'Thème WordPress', 'sans-serif' => 'Sans serif', 'serif' => 'Serif'] as $key => $label) {
            $fonts .= '<option value="' . esc_attr($key) . '"' . selected($style['font'], $key, false) . '>' . esc_html($label) . '</option>';
        }
        self::row('Typographie', $fonts . '</select>');
        echo '</tbody></table><p>Les réglages de couleur et de dimensions ne s’appliquent qu’en mode personnalisé. Le texte du bouton est choisi automatiquement pour un contraste suffisant.</p>';
        submit_button('Enregistrer l’apparence');
        echo '</form><hr><h2>Aperçu des réglages enregistrés</h2>';
        if ($effective['mode'] !== 'theme' && $effective['background'] !== 'transparent' && Appearance::contrast($effective['text'], $effective['background']) < 4.5) {
            echo '<div class="notice notice-warning inline"><p>Contraste texte/fond insuffisant : choisissez des couleurs plus contrastées (4,5:1 minimum recommandé).</p></div>';
        }
        if ($effective['mode'] === 'theme' || $effective['background'] === 'transparent') {
            echo '<p>Vérifiez le rendu sur le site public : cet aperçu utilise le fond et les styles de l’administration.</p>';
        }
        echo '<section class="convaincre-connect-widget' . ($css !== '' ? ' convaincre-connect-widget--styled' : '') . '" style="' . esc_attr($css) . '">';
        echo '<h2 class="convaincre-connect-widget__title">Exemple de formulaire</h2><p class="convaincre-connect-widget__intro">Aperçu de la charte graphique.</p>';
        echo '<div class="convaincre-connect-form__grid"><label class="convaincre-connect-field"><span class="convaincre-connect-field__label">Nom</span><input type="text" placeholder="Votre nom"></label>';
        echo '<label class="convaincre-connect-field"><span class="convaincre-connect-field__label">Courriel</span><input type="email" placeholder="vous@exemple.fr"></label></div>';
        echo '<div class="convaincre-connect-form__footer"><button type="button" class="convaincre-connect-submit">Envoyer</button></div></section></div>';
    }

    public static function saveAppearance(): void
    {
        self::guard('convaincre_connect_appearance');
        $formId = isset($_POST['form_id']) && is_string($_POST['form_id']) ? sanitize_text_field(wp_unslash($_POST['form_id'])) : '';
        if ($formId !== '' && ! in_array($formId, array_column(Manifest::forms(), 'external_form_id'), true)) {
            wp_die(esc_html__('Formulaire inconnu.', 'campagne-convaincre'));
        }
        $raw = isset($_POST['appearance']) && is_array($_POST['appearance']) ? wp_unslash($_POST['appearance']) : [];
        $style = Appearance::normalize($raw, $formId !== '');
        $stored = Appearance::stored();
        if ($formId === '') {
            $stored['site'] = $style;
        } else {
            $stored['forms'] = is_array($stored['forms'] ?? null) ? $stored['forms'] : [];
            if ($style['mode'] === 'inherit') {
                unset($stored['forms'][$formId]);
            } else {
                $stored['forms'][$formId] = $style;
            }
        }
        update_option(Appearance::OPTION, $stored, false);
        wp_safe_redirect(add_query_arg(['page' => 'convaincre-connect-appearance', 'form_id' => $formId, 'cc_saved' => '1'], admin_url('admin.php')));
        exit;
    }

    public static function save(): void
    {
        self::guard('convaincre_connect_save');
        try {
            Settings::save(
                sanitize_url(wp_unslash((string) ($_POST['api_url'] ?? ''))),
                sanitize_text_field(wp_unslash((string) ($_POST['key_id'] ?? ''))),
                isset($_POST['secret']) ? (string) wp_unslash($_POST['secret']) : null
            );
            Settings::clearManifest();
            Manifest::refresh();
            self::returnWith('connection_saved');
        } catch (\Throwable $exception) {
            self::returnWith(sanitize_key($exception->getMessage()));
        }
    }

    public static function sync(): void
    {
        self::guard('convaincre_connect_sync');
        try {
            Manifest::refresh();
            self::returnWith('manifest_synced');
        } catch (\Throwable $exception) {
            update_option(
                Settings::STATUS_OPTION,
                ['last_error_code' => sanitize_key($exception->getMessage())],
                false
            );
            self::returnWith(sanitize_key($exception->getMessage()));
        }
    }

    public static function retry(): void
    {
        self::guard('convaincre_connect_retry');
        Queue::releaseBlocked();
        Queue::process(20);
        self::returnWith('queue_retried');
    }

    private static function guard(string $action): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'campagne-convaincre'));
        }
        check_admin_referer($action);
    }

    private static function returnWith(string $notice): never
    {
        wp_safe_redirect(add_query_arg([
            'page' => 'convaincre-connect',
            'cc_notice' => sanitize_key($notice),
        ], admin_url('admin.php')));
        exit;
    }

    private static function row(string $label, string $control): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $control . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private static function actionForm(string $action, string $nonce, string $label): void
    {
        echo '<form style="display:inline-block;margin-right:8px" method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field($nonce);
        echo '<input type="hidden" name="action" value="' . esc_attr($action) . '">';
        submit_button($label, 'secondary', 'submit', false);
        echo '</form>';
    }

    private static function moduleLabel(string $module): string
    {
        $labels = [
            'petition' => __('Pétition', 'campagne-convaincre'),
            'support' => __('Soutien', 'campagne-convaincre'),
            'volunteering' => __('Bénévolat', 'campagne-convaincre'),
            'newsletter' => __('Newsletter', 'campagne-convaincre'),
            'event' => __('Événement', 'campagne-convaincre'),
            'questionnaire' => __('Questionnaire', 'campagne-convaincre'),
            'contact' => __('Contact', 'campagne-convaincre'),
        ];
        return $labels[$module] ?? $module;
    }

    private static function errorLabel(string $code): string
    {
        $labels = [
            'form_outdated' => __('Le formulaire a été modifié dans Convaincre et doit être actualisé.', 'campagne-convaincre'),
            'form_unknown' => __('Le formulaire n’existe plus dans le site Connect configuré.', 'campagne-convaincre'),
            'contact_invalid' => __('Une donnée de contact ou le choix de commune n’est pas accepté.', 'campagne-convaincre'),
            'answers_invalid' => __('Le format d’une réponse du formulaire n’est pas accepté.', 'campagne-convaincre'),
            'consent_invalid' => __('Le consentement transmis ne correspond plus au formulaire.', 'campagne-convaincre'),
            'submission_date_invalid' => __('La date technique de la réponse est invalide.', 'campagne-convaincre'),
            'submission_id_invalid' => __('L’identifiant technique de la réponse est invalide.', 'campagne-convaincre'),
            'network_error' => __('Convaincre était momentanément inaccessible.', 'campagne-convaincre'),
            'local_payload_error' => __('La réponse locale n’a pas pu être relue par le plugin.', 'campagne-convaincre'),
            'http_400' => __('Convaincre a refusé la réponse car son format est invalide.', 'campagne-convaincre'),
            'http_401' => __('Les identifiants Connect ne sont plus valides.', 'campagne-convaincre'),
            'http_403' => __('Le site WordPress n’est plus autorisé à transmettre des réponses.', 'campagne-convaincre'),
        ];
        return $labels[$code] ?? sprintf(
            /* translators: %s: technical error code. */
            __('Erreur technique : %s', 'campagne-convaincre'),
            $code
        );
    }

    private static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' s';
        }
        if ($seconds < 3600) {
            return intdiv($seconds, 60) . ' min';
        }
        return intdiv($seconds, 3600) . ' h';
    }
}
