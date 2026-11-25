<?php
declare(strict_types=1);
namespace Convaincre\Connect;

/** Simple editorial UI, using ordinary WordPress posts and native permissions. */
final class SiteNews
{
    private const META = '_cc_site_news';
    private static function guard(): void
    {
        if (!current_user_can('manage_options') || !current_user_can('edit_posts')) {
            wp_die('Accès refusé.', '', ['response'=>403]);
        }
    }
    private static function url(array $args = []): string
    {
        return add_query_arg(['page'=>'cc-site-news'] + $args, admin_url('admin.php'));
    }
    private static function redirect(string $status, int $id = 0): never
    {
        wp_safe_redirect(self::url(['cc_news'=>$status] + ($id ? ['edit'=>$id] : [])));
        exit;
    }
    private static function editable(int $id): bool
    {
        $post = get_post($id);
        return $post && $post->post_type === 'post'
            && in_array($post->post_status, ['draft','publish'], true)
            && $post->post_password === ''
            && get_post_meta($id, self::META, true) === '1'
            && current_user_can('edit_post', $id);
    }
    public static function admin(): void
    {
        self::guard();
        $id = isset($_GET['edit']) && is_scalar($_GET['edit']) ? absint($_GET['edit']) : 0;
        if ($id && !self::editable($id)) { wp_die('Cette actualité ne peut pas être modifiée ici.', '', ['response'=>403]); }
        $post = $id ? get_post($id) : null;
        $status = isset($_GET['cc_news']) && is_string($_GET['cc_news']) ? sanitize_key($_GET['cc_news']) : '';
        $messages = ['saved'=>'Actualité enregistrée.', 'published'=>'Actualité publiée.', 'trashed'=>'Actualité placée dans la corbeille.', 'missing'=>'Un titre et un texte sont nécessaires.', 'error'=>'Enregistrement impossible.'];
        echo '<div class="wrap ccsa"><h1>Mes actualités</h1>';
        SiteMode::navigation('cc-site-news');
        if (isset($messages[$status])) { echo '<div class="notice ' . (in_array($status, ['saved','published','trashed'], true) ? 'notice-success' : 'notice-error') . '"><p>' . esc_html($messages[$status]) . '</p></div>'; }
        if (!SiteMode::enabled()) {
            echo '<p>Votre site Campagne Convaincre n’est pas encore publié. Vous pouvez préparer des brouillons.</p>';
        }
        echo '<section><h2>' . ($id ? 'Modifier une actualité' : 'Nouvelle actualité') . '</h2>';
        if ($id) { echo '<p><a href="' . esc_url(self::url()) . '">Créer une nouvelle actualité</a></p>'; }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cc_site_news_save"><input type="hidden" name="post_id" value="' . esc_attr((string)$id) . '">';
        wp_nonce_field('cc_site_news_save');
        echo '<label class="ccsa-field"><strong>Titre</strong><input required maxlength="240" name="title" value="' . esc_attr($post->post_title ?? '') . '"></label><label class="ccsa-field"><strong>Texte</strong><textarea required name="body" rows="12">' . esc_textarea($post ? wp_strip_all_tags($post->post_content) : '') . '</textarea></label>';
        SiteMode::image('image', $id ? (int)get_post_thumbnail_id($id) : 0, 'Photographie principale');
        if (!$post || $post->post_status !== 'publish') {
            echo '<button class="button" name="intent" value="draft">Enregistrer le brouillon</button> ';
        }
        if (SiteMode::enabled() && current_user_can('publish_posts')) {
            echo '<button class="button button-primary" name="intent" value="publish">' . ($post && $post->post_status === 'publish' ? 'Mettre à jour l’actualité' : 'Publier l’actualité') . '</button>';
        }
        echo '</form></section><section><h2>Mes publications</h2>';
        $paged = isset($_GET['news_page']) && is_scalar($_GET['news_page']) ? max(1,absint($_GET['news_page'])) : 1;
        $q = new \WP_Query(['post_type'=>'post','post_status'=>['draft','publish'],'posts_per_page'=>20,'paged'=>$paged,'meta_key'=>self::META,'meta_value'=>'1','orderby'=>'modified','order'=>'DESC']);
        if (!$q->posts) { echo '<p>Aucune actualité créée avec Campagne Convaincre.</p>'; }
        foreach ($q->posts as $item) {
            if (!self::editable((int)$item->ID)) { continue; }
            echo '<div class="ccsa-form-row"><div><strong>' . esc_html($item->post_title) . '</strong><p>' . ($item->post_status === 'publish' ? 'Publiée' : 'Brouillon') . '</p></div><div><a class="button" href="' . esc_url(self::url(['edit'=>$item->ID])) . '">Modifier</a> ';
            if ($item->post_status === 'publish') { echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url(get_permalink($item)) . '">Voir</a> '; }
            if (current_user_can('delete_post', $item->ID)) {
                echo '<form class="ccsa-inline" method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="cc_site_news_trash"><input type="hidden" name="post_id" value="' . esc_attr((string)$item->ID) . '">';
                wp_nonce_field('cc_site_news_trash');
                echo '<button class="button">Mettre à la corbeille</button></form>';
            }
            echo '</div></div>';
        }
        if ($paged > 1) { echo '<a class="button" href="' . esc_url(self::url(['news_page'=>$paged-1])) . '">Précédent</a> '; }
        if ($paged < $q->max_num_pages) { echo '<a class="button" href="' . esc_url(self::url(['news_page'=>$paged+1])) . '">Suivant</a>'; }
        echo '<p>Les articles créés auparavant dans WordPress restent accessibles dans <a href="' . esc_url(admin_url('edit.php')) . '">Articles</a>.</p></section></div>';
    }
    public static function save(): void
    {
        self::guard();
        check_admin_referer('cc_site_news_save');
        $id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if ($id && !self::editable($id)) { wp_die('Accès refusé.', '', ['response'=>403]); }
        $title = isset($_POST['title']) && is_string($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $body = isset($_POST['body']) && is_string($_POST['body']) ? sanitize_textarea_field(wp_unslash($_POST['body'])) : '';
        if ($title === '' || $body === '') { self::redirect('missing',$id); }
        $publish = isset($_POST['intent']) && is_string($_POST['intent']) && sanitize_key(wp_unslash($_POST['intent'])) === 'publish';
        $current = $id ? get_post_status($id) : 'draft';
        if (($publish || $current === 'publish') && (!SiteMode::enabled() || !current_user_can('publish_posts'))) {
            wp_die('Publication non autorisée.', '', ['response'=>403]);
        }
        $status = $publish || $current === 'publish' ? 'publish' : 'draft';
        $data = ['post_type'=>'post','post_title'=>$title,'post_content'=>$body,'post_status'=>$status,'meta_input'=>[self::META=>'1']];
        $result = $id ? wp_update_post(wp_slash(['ID'=>$id] + $data), true) : wp_insert_post(wp_slash($data), true);
        if (is_wp_error($result) || !$result) { self::redirect('error',$id); }
        $image = isset($_POST['image']) && is_scalar($_POST['image']) ? absint($_POST['image']) : 0;
        if ($image && wp_attachment_is_image($image)) { set_post_thumbnail((int)$result, $image); }
        else { delete_post_thumbnail((int)$result); }
        self::redirect($status === 'publish' ? 'published' : 'saved', (int)$result);
    }
    public static function trash(): void
    {
        self::guard();
        check_admin_referer('cc_site_news_trash');
        $id = isset($_POST['post_id']) && is_scalar($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        if (!self::editable($id) || !current_user_can('delete_post',$id)) { wp_die('Accès refusé.', '', ['response'=>403]); }
        if (defined('EMPTY_TRASH_DAYS') && !EMPTY_TRASH_DAYS) { wp_die('La corbeille WordPress est désactivée. Utilisez les réglages WordPress pour gérer la suppression.'); }
        self::redirect(wp_trash_post($id) ? 'trashed' : 'error');
    }
}
