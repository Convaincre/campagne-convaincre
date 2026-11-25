<?php

declare(strict_types=1);

namespace Convaincre\Connect;

final class Shortcodes
{
    private const MODULE_TAGS = [
        'petition' => 'convaincre_petition',
        'support' => 'convaincre_soutien',
        'volunteering' => 'convaincre_benevolat',
        'newsletter' => 'convaincre_newsletter',
        'event' => 'convaincre_evenement',
        'questionnaire' => 'convaincre_questionnaire',
        'contact' => 'convaincre_contact',
    ];

    public static function register(): void
    {
        add_shortcode('convaincre_form', [Form::class, 'shortcode']);
        foreach (self::MODULE_TAGS as $tag) {
            add_shortcode($tag, [self::class, 'render']);
        }
    }

    public static function render(mixed $attributes = [], mixed $content = null, string $tag = ''): string
    {
        unset($content);
        $module = self::moduleForTag($tag);
        if ($module === null) {
            return '';
        }
        return Form::shortcodeForModule(
            is_array($attributes) ? $attributes : [],
            $module,
            $tag
        );
    }

    public static function moduleForTag(string $tag): ?string
    {
        $module = array_search($tag, self::MODULE_TAGS, true);
        return is_string($module) ? $module : null;
    }

    public static function tagForModule(string $module): ?string
    {
        return self::MODULE_TAGS[$module] ?? null;
    }

    public static function forBinding(array $binding): string
    {
        $tag = self::tagForModule((string) ($binding['module'] ?? ''))
            ?? 'convaincre_form';
        $externalId = (string) ($binding['external_form_id'] ?? '');

        return '[' . $tag . ' id="' . $externalId . '"]';
    }

    public static function moduleTags(): array
    {
        return self::MODULE_TAGS;
    }
}
