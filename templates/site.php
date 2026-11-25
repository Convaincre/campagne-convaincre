<?php
declare(strict_types=1);
use Convaincre\Connect\SiteMode;
use Convaincre\Connect\SiteAppearance;
use Convaincre\Connect\Form;
use Convaincre\Connect\Agenda;
if (!defined('ABSPATH')) { exit; }
(static function ($ccPreview, $ccPreviewSection): void {
$ccPreview = !empty($ccPreview);
$d = $ccPreview ? SiteMode::draftData() : SiteMode::data();
$section = $ccPreview ? $ccPreviewSection : SiteMode::section();
$links = SiteMode::links($d, $ccPreview);
$appearance = SiteAppearance::normalize($d);
$color = $appearance['color'];
$model = $appearance['model'];
$secondary = $appearance['secondary'];
$layout = SiteAppearance::layout($appearance);
$election = SiteMode::electionLabel($d);
$socials = SiteMode::socialLinks($d);
$supporters = SiteMode::supporters($d);
$primary = $secondaryAction = null;
$mode = SiteMode::actionMode($d['cta_mode'] ?? 'hero');
if ($section === 'home' && isset($links['forms'])) {
    $available = $ccPreview ? \Convaincre\Connect\Manifest::forms() : SiteMode::publicForms($d);
    $primary = SiteMode::contactForm($d, $available);
    $secondaryAction = SiteMode::actionForm($d, $available, 'cta_secondary_form');
    if ($primary && $secondaryAction && $primary['external_form_id'] === $secondaryAction['external_form_id']) { $secondaryAction = null; }
}
$actionButton = static function (?array $form, bool $secondary = false) use ($d, $links, $ccPreview): void {
    if (!$form || !isset($links['forms'])) { return; }
    $url = $ccPreview ? $links['forms']['url'] : add_query_arg('cc_form', (string)$form['external_form_id'], $links['forms']['url']);
    $label = ($d[$secondary ? 'cta_secondary_button' : 'cta_button'] ?? '') ?: SiteMode::CTA_MODULES[$form['module']][1];
    echo '<a class="ccs-action' . ($secondary ? ' ccs-action-secondary' : ' ccs-action-primary') . '" href="' . esc_url($url) . '">' . esc_html($label) . ' <span aria-hidden="true">↗</span></a>';
};
$paragraph = static function ($value): void { if ($value !== '') { echo '<p class="ccs-copy">' . nl2br(esc_html((string)$value)) . '</p>'; } };
$image = static function ($id, $size = 'large'): void { if ($id) { echo wp_get_attachment_image((int)$id, $size, false, ['class'=>'ccs-image']); } };
$news = static function (int $limit) use ($ccPreview): void {
    $paged = max(1, (int)get_query_var('paged'), (int)get_query_var('page'));
    $q = new WP_Query(['post_type'=>'post','post_status'=>'publish','posts_per_page'=>$limit,'paged'=>$limit===3?1:$paged,'ignore_sticky_posts'=>true,'has_password'=>false]);
    if (!$q->have_posts()) { echo '<p>Aucune actualité pour le moment.</p>'; return; }
    echo '<div class="ccs-grid">';
    while ($q->have_posts()) { $q->the_post();
        echo '<article class="ccs-card">';
        if (has_post_thumbnail()) { the_post_thumbnail('medium_large', ['class'=>'ccs-image']); }
        echo '<p class="ccs-muted">' . esc_html(get_the_date()) . '</p><h3>' . ($ccPreview ? esc_html(get_the_title()) : '<a href="' . esc_url(get_permalink()) . '">' . esc_html(get_the_title()) . '</a>') . '</h3><p>' . esc_html(wp_trim_words(wp_strip_all_tags(get_the_excerpt()), 25)) . '</p></article>';
    }
    echo '</div>';
    if (!$ccPreview && $limit !== 3 && $q->max_num_pages > 1) { echo '<nav aria-label="Pagination">' . wp_kses_post(paginate_links(['total'=>$q->max_num_pages,'current'=>$paged])) . '</nav>'; }
    wp_reset_postdata();
};
// Render first: Connect and WordPress blocks can enqueue their assets before wp_head.
// Form::shortcode() and Agenda::render() escape dynamic values when building markup.
// Do not apply wp_kses_post() to their complete HTML: it removes form/input/select elements.
// WordPress article content follows the core the_content() rendering and permission model.
ob_start();
if ($section === 'home') {
    ?>
    <section class="ccs-hero<?php echo esc_attr(empty($d['hero']) ? ' ccs-hero-no-image' : ''); ?>">
        <div class="ccs-hero-copy">
            <?php if (!empty($d['territory'])) { ?><p class="ccs-eyebrow"><?php echo esc_html($d['territory']); ?></p><?php } ?>
            <h1><?php echo esc_html($d['slogan'] ?? ''); ?></h1>
            <p class="ccs-name"><?php echo esc_html($d['name'] ?? ''); ?></p>
            <?php $paragraph($d['intro'] ?? ''); ?>
            <?php if (($primary && $mode === 'hero') || $secondaryAction) { ?><div class="ccs-actions"><?php if ($mode === 'hero') { $actionButton($primary); } $actionButton($secondaryAction, true); ?></div><?php } ?>
        </div>
        <?php if (!empty($d['hero'])) { ?><div class="ccs-hero-image"><?php $image($d['hero'], 'large'); ?></div><?php } ?>
    </section>
    <?php if ($primary && ($mode !== 'hero' || !empty($d['cta_title']) || !empty($d['cta_text']))) { ?>
        <section class="ccs-wrap ccs-cta-wrap" aria-label="Formulaire à la une">
            <div class="ccs-cta<?php echo esc_attr($mode === 'inline' ? ' ccs-cta-inline' : ''); ?>">
                <div class="ccs-cta-copy"><p class="ccs-eyebrow"><?php echo esc_html(SiteMode::CTA_MODULES[$primary['module']][0]); ?></p>
                <h2><?php echo esc_html(($d['cta_title'] ?? '') ?: ($primary['name'] ?? SiteMode::CTA_MODULES[$primary['module']][1])); ?></h2>
                <?php $paragraph($d['cta_text'] ?? ''); ?></div>
                <?php if ($mode === 'inline') {
                    if ($ccPreview) { echo '<div class="ccs-form-preview"><strong>Aperçu du formulaire</strong><p>Les champs et l’envoi seront disponibles sur le site publié.</p><span aria-hidden="true"></span><span aria-hidden="true"></span>'; $actionButton($primary); echo '</div>'; }
                    else { echo '<div class="ccs-form">' . wp_kses(Form::shortcode(['id'=>(string)$primary['external_form_id']]), Form::allowedMarkup()) . '</div>'; }
                } else { $actionButton($primary); } ?>
            </div>
        </section>
    <?php } ?>
    <?php
    $summaries = [
        'team'=>['01', implode(' · ', array_slice(array_filter(array_column($d['team'] ?? [], 'name')), 0, 3))],
        'project'=>['02', implode(' · ', array_slice(array_filter(array_column($d['project'] ?? [], 'title')), 0, 3))],
        'territory'=>['03', ($d['territory_text'] ?? '') ?: ($d['territory'] ?? '')],
        'agenda'=>['04', 'Dates et événements publics'],
        'forms'=>['05', 'Formulaires et réseaux sociaux'],
    ];
    $homeLinks = array_intersect_key($links, $summaries);
    if ($homeLinks) { ?>
    <section class="ccs-wrap ccs-discover"><div class="ccs-section-heading"><p class="ccs-eyebrow">En savoir plus</p><h2>Découvrir</h2></div><div class="ccs-grid ccs-discover-grid">
    <?php foreach ($homeLinks as $key=>$link) { ?>
        <article class="ccs-card ccs-discover-card"><span class="ccs-card-index" aria-hidden="true"><?php echo esc_html($summaries[$key][0]); ?></span><h3><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?> <span aria-hidden="true">↗</span></a></h3><?php if ($summaries[$key][1] !== '') { ?><p><?php echo esc_html(wp_trim_words($summaries[$key][1], 16)); ?></p><?php } ?></article>
    <?php } ?></div></section>
    <?php } ?>
    <?php if (isset($links['news'])) { ?><section class="ccs-wrap ccs-news-home"><div class="ccs-section-heading"><p class="ccs-eyebrow">Le journal</p><h2>Dernières actualités</h2><a href="<?php echo esc_url($links['news']['url']); ?>">Toutes les actualités <span aria-hidden="true">↗</span></a></div><?php $news(3); ?></section><?php } ?>
    <?php
} else {
    echo '<section class="ccs-wrap ccs-inner">';
    if ($section !== 'article') { echo '<h1>' . esc_html(SiteMode::heading((string)$section, $d)) . '</h1>'; }
    switch ($section) {
        case 'team':
            echo '<div class="ccs-grid">';
            foreach ($d['team'] ?? [] as $person) { echo '<article class="ccs-card">'; $image($person['image'] ?? 0, 'medium_large'); echo '<h2>' . esc_html($person['name']) . '</h2>'; $paragraph($person['role']); $paragraph($person['bio']); echo '</article>'; }
            echo '</div>'; break;
        case 'project':
            foreach ($d['project'] ?? [] as $item) { echo '<article class="ccs-card ccs-project"><h2>' . esc_html($item['title']) . '</h2>'; $paragraph($item['body']); echo '</article>'; } break;
        case 'territory':
            echo '<h2>' . esc_html($d['territory'] ?? '') . '</h2>'; $image($d['territory_image'] ?? 0, 'large'); $paragraph($d['territory_text'] ?? ''); break;
        case 'news': $news(12); break;
        case 'agenda':
            if ($ccPreview) { echo '<p>Les événements publics apparaîtront ici après publication. Les inscriptions sont désactivées dans cet aperçu.</p>'; }
            else { echo \Convaincre\Connect\Settings::configured() ? wp_kses(Agenda::render(), Form::allowedMarkup()) : '<p>Agenda indisponible actuellement.</p>'; } break;
        case 'forms':
            if ($socials) {
                echo '<h2>Nos réseaux sociaux</h2><div class="ccs-socials" aria-label="Réseaux sociaux">';
                foreach ($socials as $key=>$social) {
                    echo '<a class="ccs-social-link ccs-social-' . esc_attr($key) . '" href="' . esc_url($social['url']) . '" target="_blank" rel="noopener noreferrer"><span class="ccs-social-symbol">' . wp_kses(SiteMode::socialIcon($key), ['img'=>['class'=>true,'src'=>true,'alt'=>true,'width'=>true,'height'=>true,'aria-hidden'=>true]]) . '</span><span>' . esc_html($social['label']) . '</span><span class="ccs-social-arrow" aria-hidden="true">↗</span></a>';
                }
                echo '</div>';
            }
            if ($ccPreview) {
                echo '<p>Les formulaires sélectionnés apparaîtront ici. Les envois sont désactivés dans cet aperçu.</p>';
                foreach (\Convaincre\Connect\Manifest::forms() as $form) {
                    if (in_array((string)($form['external_form_id'] ?? ''), $d['forms'] ?? [], true)) {
                        echo '<article class="ccs-card"><h2>' . esc_html((string)($form['name'] ?? 'Formulaire')) . '</h2></article>';
                    }
                }
                break;
            }
            $forms = SiteMode::publicForms($d);
            if (!$forms && !$socials) { echo '<p>Aucun formulaire disponible actuellement.</p>'; }
            $selected = isset($_GET['cc_form']) && is_string($_GET['cc_form']) ? sanitize_text_field(wp_unslash($_GET['cc_form'])) : '';
            $active = null;
            foreach ($forms as $form) {
                if ((string)$form['external_form_id'] === $selected) { $active = $form; break; }
            }
            if ($active !== null) {
                echo '<p><a href="' . esc_url(get_permalink((int)$d['pages']['forms'])) . '">Retour à ' . esc_html(SiteMode::heading('forms', $d)) . '</a></p>';
                echo '<article class="ccs-form">' . wp_kses(Form::shortcode(['id'=>(string)$active['external_form_id']]), Form::allowedMarkup()) . '</article>';
            } else {
                if ($forms) { echo '<h2>Les formulaires</h2><div class="ccs-grid ccs-follow-grid">'; }
                foreach ($forms as $form) {
                    $module = SiteMode::CTA_MODULES[$form['module'] ?? ''] ?? ['Formulaire','Ouvrir'];
                    echo '<article class="ccs-card ccs-follow-card"><p class="ccs-eyebrow">' . esc_html($module[0]) . '</p><h3><a href="' . esc_url(add_query_arg('cc_form', (string)$form['external_form_id'], get_permalink((int)$d['pages']['forms']))) . '">' . esc_html((string)($form['name'] ?? $form['external_form_id'])) . '</a></h3><span class="ccs-follow-arrow" aria-hidden="true">↗</span></article>';
                }
                if ($forms) { echo '</div>'; }
            }
            break;
        case 'legal': case 'privacy': $paragraph($d[$section] ?? ''); break;
        case 'article':
            while (have_posts()) { the_post(); echo '<article class="ccs-article"><h1>' . esc_html(get_the_title()) . '</h1><p class="ccs-muted">' . esc_html(get_the_date()) . '</p>'; if (!post_password_required() && has_post_thumbnail()) { the_post_thumbnail('large', ['class'=>'ccs-image']); } the_content(); wp_link_pages(); echo '</article>'; } break;
    }
    echo '</section>';
}
$body = ob_get_clean();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width, initial-scale=1">
<?php if ($ccPreview) { ?><meta name="robots" content="noindex,nofollow,noarchive"><title>Aperçu privé</title><?php $previewIcon = !empty($d['logo']) ? wp_get_attachment_image_url((int)$d['logo'], [32,32]) : false; if ($previewIcon) { ?><link rel="icon" href="<?php echo esc_url($previewIcon); ?>"><?php } ?><?php wp_enqueue_style('cc-site', CONVAINCRE_CONNECT_URL . 'assets/site.css', [], CONVAINCRE_CONNECT_VERSION); wp_print_styles('cc-site'); ?><?php } else { if (!current_theme_supports('title-tag')) { ?><title><?php echo esc_html(wp_get_document_title()); ?></title><?php } wp_head(); ?><?php wp_enqueue_style('cc-site', CONVAINCRE_CONNECT_URL . 'assets/site.css', [], CONVAINCRE_CONNECT_VERSION); wp_print_styles('cc-site'); ?><?php } ?></head>
<body <?php if ($ccPreview) { echo 'class="ccs-site"'; } else { body_class('ccs-site'); } ?> style="<?php echo esc_attr('--ccs-accent:' . $color . ';--ccs-ink:' . SiteMode::ink($color) . ';--ccs-secondary:' . $secondary . ';--ccs-secondary-ink:' . SiteMode::ink($secondary)); ?>">
<?php if (!$ccPreview) { wp_body_open(); } ?>
<div data-cc-publication="<?php echo esc_attr($ccPreview ? 'preview' : (string)($d['publication_id'] ?? 'initial')); ?>" class="ccs-shell ccs-<?php echo esc_attr($model); ?> ccs-layout-<?php echo esc_attr($layout); ?> ccs-font-<?php echo esc_attr($appearance['font']); ?>">
<a class="ccs-skip" href="#ccs-main">Aller au contenu</a>
<header class="ccs-header"><div class="ccs-header-inner">
<a class="ccs-brand" href="<?php echo esc_url($ccPreview ? SiteMode::previewUrl() : home_url('/')); ?>"><?php $image($d['logo'] ?? 0, 'thumbnail'); ?><span><?php echo esc_html($d['name'] ?? ''); ?></span></a>
<div class="ccs-nav-desktop"><nav class="ccs-nav" aria-label="Navigation principale"><?php foreach ($links as $key=>$link) { if (in_array($key,['legal','privacy'],true)) { continue; } ?><a href="<?php echo esc_url($link['url']); ?>"<?php if ($key === $section) { echo ' aria-current="page"'; } ?>><?php echo esc_html($link['label']); ?></a><?php } ?></nav></div>
<details class="ccs-nav-mobile"><summary>Menu</summary><nav class="ccs-nav" aria-label="Navigation principale"><?php foreach ($links as $key=>$link) { if (in_array($key,['legal','privacy'],true)) { continue; } ?><a href="<?php echo esc_url($link['url']); ?>"<?php if ($key === $section) { echo ' aria-current="page"'; } ?>><?php echo esc_html($link['label']); ?></a><?php } ?></nav></details>
</div><?php if ($election !== '') { ?><div class="ccs-election"><?php echo esc_html($election); ?></div><?php } ?></header>
<main id="ccs-main" tabindex="-1"><?php
    // Core the_content() can render trusted blocks and third-party shortcodes, including embeds.
    // Managed page HTML, including form controls, is filtered with the explicit allowlist.
    if ($section === 'article') {
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core article rendering, with individual template fields escaped at their output points.
        echo $body;
    } else {
        echo wp_kses($body, Form::allowedMarkup());
    }
    ?></main>
<footer class="ccs-footer"><div class="ccs-wrap"><div class="ccs-footer-top"><div><strong><?php echo esc_html($d['name'] ?? ''); ?></strong><?php if ($election !== '') { ?><p class="ccs-election-footer"><?php echo esc_html($election); ?></p><?php } ?></div><?php if ($socials && isset($links['forms'])) { ?><div class="ccs-footer-socials" aria-label="Réseaux sociaux"><?php foreach ($socials as $key=>$social) { ?><a href="<?php echo esc_url($social['url']); ?>" aria-label="<?php echo esc_attr($social['label']); ?>" target="_blank" rel="noopener noreferrer"><?php echo wp_kses(SiteMode::socialIcon($key), ['img'=>['class'=>true,'src'=>true,'alt'=>true,'width'=>true,'height'=>true,'aria-hidden'=>true]]); ?></a><?php } ?></div><?php } ?></div><?php if ($supporters) { ?><section class="ccs-supporters" aria-labelledby="ccs-supporters-title"><h2 id="ccs-supporters-title">Avec le soutien de</h2><ul class="ccs-supporters-list"><?php foreach ($supporters as $supporter) { ?><li><div class="ccs-support-logo"><?php echo wp_get_attachment_image($supporter['image'], 'medium', false, ['class'=>'ccs-support-image','alt'=>'','loading'=>'lazy']); ?></div><span><?php echo esc_html($supporter['name']); ?></span></li><?php } ?></ul></section><?php } ?><nav aria-label="Informations légales"><?php foreach (['legal','privacy'] as $key) { if (isset($links[$key])) { ?><a href="<?php echo esc_url($links[$key]['url']); ?>"><?php echo esc_html($links[$key]['label']); ?></a><?php } } ?></nav></div></footer>
</div><?php if (!$ccPreview) { wp_footer(); } ?></body></html>
<?php })(!empty($ccPreview), $ccPreviewSection ?? 'home'); ?>
