<?php

declare(strict_types=1);

namespace Convaincre\Connect;

/** Dynamic blocks use the same public renderers as the existing shortcodes. */
final class Blocks
{
    public static function register(): void
    {
        add_action('init', [self::class, 'registerTypes']);
        add_action('enqueue_block_editor_assets', [self::class, 'editorAssets']);
    }

    public static function registerTypes(): void
    {
        wp_register_script(
            'convaincre-connect-blocks',
            CONVAINCRE_CONNECT_URL . 'assets/blocks.js',
            ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n'],
            CONVAINCRE_CONNECT_VERSION,
            true
        );
        foreach (['form' => 'renderForm', 'agenda' => 'renderAgenda'] as $name => $callback) {
            register_block_type('convaincre-connect/' . $name, [
                'api_version' => 3,
                'attributes' => ['id' => ['type' => 'string', 'default' => '']],
                'supports' => ['html' => false],
                'editor_script' => 'convaincre-connect-blocks',
                'render_callback' => [self::class, $callback],
            ]);
        }
    }

    public static function catalog(array $forms, array $events): array
    {
        $result = ['forms' => [], 'events' => []];
        foreach ($forms as $form) {
            if (! is_array($form) || ! is_string($form['external_form_id'] ?? null) || $form['external_form_id'] === '') {
                continue;
            }
            $result['forms'][] = [
                'id' => $form['external_form_id'],
                'label' => (string) ($form['name'] ?? $form['external_form_id']),
            ];
        }
        foreach ($events as $event) {
            if (! is_array($event) || ! is_string($event['id'] ?? null) || $event['id'] === '') {
                continue;
            }
            $result['events'][] = [
                'id' => $event['id'],
                'label' => (string) ($event['title'] ?? $event['id']),
            ];
        }
        return $result;
    }

    public static function editorAssets(): void
    {
        if (! current_user_can('edit_posts') && ! current_user_can('edit_pages')) {
            return;
        }
        // Use the locally synchronized manifest only; never send credentials,
        // binding tokens, answers, consents or contact details to the editor.
        $stored = Manifest::stored();
        $data = is_array($stored['data'] ?? null) ? $stored['data'] : [];
        $catalog = self::catalog(
            is_array($data['forms'] ?? null) ? $data['forms'] : [],
            is_array($data['agenda'] ?? null) ? $data['agenda'] : []
        );
        wp_localize_script('convaincre-connect-blocks', 'ConvaincreConnectBlocks', $catalog);
        wp_enqueue_script('convaincre-connect-blocks');
    }

    private static function id(array $attributes): string
    {
        return is_string($attributes['id'] ?? null) ? substr(sanitize_text_field($attributes['id']), 0, 120) : '';
    }

    public static function renderForm(array $attributes): string
    {
        $id = self::id($attributes);
        if ($id === '') {
            return '';
        }
        return Form::shortcode(['id' => $id]);
    }

    public static function renderAgenda(array $attributes): string
    {
        $id = self::id($attributes);
        // An invalid specific ID must never silently expand to all events.
        if (isset($attributes['id']) && $attributes['id'] !== '' && $id === '') {
            return '';
        }
        return Agenda::render(['id' => $id]);
    }
}
