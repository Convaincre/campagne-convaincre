<?php

declare(strict_types=1);

namespace Convaincre\Connect;

final class Agenda
{
    public static function register(): void
    {
        add_shortcode('convaincre_agenda', [self::class, 'render']);
    }

    public static function render(array|string $attributes = []): string
    {
        $attributes = shortcode_atts(['id' => ''], is_array($attributes) ? $attributes : [], 'convaincre_agenda');
        $id = sanitize_text_field((string) $attributes['id']);
        wp_enqueue_style('convaincre-connect-forms', CONVAINCRE_CONNECT_URL . 'assets/forms.css', [], CONVAINCRE_CONNECT_VERSION);
        $html = '<section class="convaincre-connect-agenda">';
        $count = 0;
        foreach (Manifest::agenda() as $event) {
            if (! is_array($event) || ($id !== '' && ($event['id'] ?? '') !== $id)) {
                continue;
            }
            $start = strtotime((string) ($event['starts_at'] ?? ''));
            $end = strtotime((string) ($event['ends_at'] ?? ''));
            if ($start === false || ($end !== false ? $end : $start) < time()) {
                continue;
            }
            ++$count;
            $html .= '<article class="convaincre-connect-agenda__event"><h3>' . esc_html((string) ($event['title'] ?? '')) . '</h3>';
            $html .= '<p><time datetime="' . esc_attr(gmdate('c', $start)) . '">' . esc_html(wp_date(get_option('date_format') . ' · ' . get_option('time_format'), $start)) . '</time></p>';
            $html .= '<p>' . esc_html((string) ($event['location'] ?? '')) . '</p>';
            $html .= '<p>' . nl2br(esc_html((string) ($event['description'] ?? ''))) . '</p>';
            $form = (string) ($event['external_form_id'] ?? '');
            $state = (string) ($event['registration_state'] ?? 'open');
            if ($state === 'closed') {
                $html .= '<p>' . esc_html__('Inscriptions closes.', 'campagne-convaincre') . '</p>';
            } elseif ($form !== '') {
                $label = $state === 'waitlist' ? __('Complet — demander une place en liste d’attente', 'campagne-convaincre') : __('Demander une inscription', 'campagne-convaincre');
                $html .= '<details><summary>' . esc_html($label) . '</summary>';
                $html .= Form::shortcodeForModule(['id' => $form], 'event', 'convaincre_evenement') . '</details>';
            }
            $html .= '</article>';
        }
        if ($count === 0) {
            $html .= '<p>' . esc_html__('Aucun événement disponible actuellement.', 'campagne-convaincre') . '</p>';
        }
        return $html . '</section>';
    }
}
