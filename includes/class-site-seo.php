<?php
declare(strict_types=1);
namespace Convaincre\Connect;

/** Basic public document metadata, scoped to the optional site mode. */
final class SiteSeo
{
    public static function register(): void
    {
        add_filter('pre_get_document_title', [self::class, 'title'], 30);
        add_action('wp_head', [self::class, 'head'], 5);
        add_filter('wp_robots', [self::class, 'robots']);
    }

    private static function externalSeo(): bool
    {
        return defined('WPSEO_VERSION') || defined('RANK_MATH_VERSION')
            || defined('AIOSEO_VERSION') || defined('SEOPRESS_VERSION')
            || defined('THE_SEO_FRAMEWORK_VERSION');
    }

    public static function text(string $value, int $limit = 160): string
    {
        $value = html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        $limit = max(1, $limit);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($value, 'UTF-8') > $limit
                ? rtrim(mb_substr($value, 0, max(0, $limit-1), 'UTF-8')) . '…' : $value;
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return is_array($characters) && count($characters) > $limit
            ? rtrim(implode('', array_slice($characters, 0, $limit-1))) . '…' : $value;
    }

    /** Pure title/description policy, also used in the tests. */
    public static function metadata(string $section, array $site, array $article = []): array
    {
        $name = self::text((string)($site['name'] ?? ''), 100);
        $territory = self::text((string)($site['territory'] ?? ''), 100);
        $heading = $section === 'article' ? (string)($article['title'] ?? '') : SiteMode::heading($section, $site);
        $title = $section === 'home'
            ? implode(' — ', array_filter([$name, $territory], static fn($part)=>$part !== ''))
            : implode(' — ', array_filter([$heading, $name], static fn($part)=>$part !== ''));
        $description = match ($section) {
            'home' => (string)($site['intro'] ?? ''),
            'territory' => (string)($site['territory_text'] ?? ''),
            'article' => (string)($article['description'] ?? ''),
            default => '',
        };
        $description = self::text($description);
        if ($description === '' && $section !== 'article') {
            $description = self::text(implode(' — ', array_filter([$section === 'home' ? '' : $heading, $name, $territory], static fn($part)=>$part !== '')));
        }
        return ['title'=>self::text($title, 160), 'description'=>$description];
    }

    private static function current(): ?array
    {
        if (self::externalSeo() || is_admin() || !is_singular()) { return null; }
        $section = SiteMode::section();
        if ($section === null) { return null; }
        $post = get_queried_object();
        if (!$post instanceof \WP_Post || $post->post_status !== 'publish' || $post->post_password !== '') {
            return null;
        }
        $site = SiteMode::data();
        $article = [];
        $image = (int)($site['hero'] ?? 0);
        if ($section === 'article') {
            // Strip shortcodes without executing them; never extract protected posts.
            $source = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
            $article = ['title'=>$post->post_title,'description'=>wp_strip_all_tags(strip_shortcodes($source), true)];
            $image = (int)get_post_thumbnail_id($post->ID) ?: $image;
        } elseif ($section === 'territory') {
            $image = (int)($site['territory_image'] ?? 0) ?: $image;
        }
        $meta = self::metadata($section, $site, $article);
        $meta['url'] = wp_get_canonical_url($post->ID) ?: get_permalink($post->ID);
        $meta['image'] = '';
        $meta['image_alt'] = '';
        $image = $image ?: (int)($site['logo'] ?? 0);
        if ($image && wp_attachment_is_image($image) && get_post_status($image) === 'publish') {
            $meta['image'] = wp_get_attachment_image_url($image, 'large') ?: '';
            $meta['image_alt'] = self::text((string)get_post_meta($image, '_wp_attachment_image_alt', true));
        }
        $meta['name'] = self::text((string)($site['name'] ?? ''), 100);
        $meta['section'] = $section;
        return $meta;
    }

    public static function title(string $title): string
    {
        $meta = self::current();
        return $meta && $meta['title'] !== '' ? esc_html($meta['title']) : $title;
    }

    public static function robots(array $robots): array
    {
        // Form selections are interaction URLs, not additional indexable pages.
        if (!is_admin() && SiteMode::section() === 'forms' && isset($_GET['cc_form'])) {
            $robots['noindex'] = true;
            unset($robots['index']);
        }
        return $robots;
    }

    public static function head(): void
    {
        $meta = self::current();
        if (!$meta) { return; }
        $tags = [
            'og:title'=>$meta['title'],
            'og:description'=>$meta['description'],
            'og:type'=>$meta['section'] === 'article' ? 'article' : 'website',
            'og:site_name'=>$meta['name'],
            'og:url'=>$meta['url'],
            'og:image'=>$meta['image'],
            'og:image:alt'=>$meta['image_alt'],
        ];
        if ($meta['description'] !== '') {
            echo '<meta name="description" content="' . esc_attr($meta['description']) . '">' . "\n";
        }
        foreach ($tags as $key=>$value) {
            if (is_string($value) && $value !== '') {
                echo '<meta property="' . esc_attr($key) . '" content="' . esc_attr($value) . '">' . "\n";
            }
        }
        $twitter = ['twitter:card'=>$meta['image'] ? 'summary_large_image' : 'summary','twitter:title'=>$meta['title'],'twitter:description'=>$meta['description'],'twitter:image'=>$meta['image'],'twitter:image:alt'=>$meta['image_alt']];
        foreach ($twitter as $key=>$value) {
            if ($value !== '') { echo '<meta name="' . esc_attr($key) . '" content="' . esc_attr($value) . '">' . "\n"; }
        }
        // WordPress owns rel=canonical, robots and wp-sitemap.xml; do not duplicate them.
    }
}
