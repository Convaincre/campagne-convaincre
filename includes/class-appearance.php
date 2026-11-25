<?php

declare(strict_types=1);

namespace Convaincre\Connect;

final class Appearance
{
    public const OPTION = 'convaincre_connect_appearance';
    public const DEFAULTS = [
        'mode' => 'theme',
        'primary' => '#a52b43',
        'background' => '#ffffff',
        'text' => '#202124',
        'field' => '#ffffff',
        'border' => '#737373',
        'radius' => '8',
        'spacing' => '16',
        'width' => '760',
        'font' => 'inherit',
        'size' => '16',
    ];

    public static function normalize(array $raw, bool $override = false): array
    {
        $result = self::DEFAULTS;
        $modes = $override ? ['inherit', 'theme', 'convaincre', 'custom'] : ['theme', 'convaincre', 'custom'];
        $mode = $raw['mode'] ?? ($override ? 'inherit' : 'theme');
        $result['mode'] = is_string($mode) && in_array($mode, $modes, true) ? $mode : ($override ? 'inherit' : 'theme');
        foreach (['primary', 'background', 'text', 'field', 'border'] as $key) {
            $value = $raw[$key] ?? '';
            if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/D', $value)) {
                $result[$key] = strtolower($value);
            }
        }
        if (($raw['background'] ?? null) === 'transparent') {
            $result['background'] = 'transparent';
        }
        foreach (['radius' => [0, 32], 'spacing' => [8, 40], 'width' => [280, 1200], 'size' => [14, 24]] as $key => [$min, $max]) {
            $value = $raw[$key] ?? '';
            if ((is_string($value) || is_int($value)) && preg_match('/^\d{1,4}$/D', (string) $value)) {
                $result[$key] = (string) max($min, min($max, (int) $value));
            }
        }
        $font = $raw['font'] ?? '';
        if (is_string($font) && in_array($font, ['inherit', 'sans-serif', 'serif'], true)) {
            $result['font'] = $font;
        }
        return $result;
    }

    public static function stored(): array
    {
        $stored = get_option(self::OPTION, []);
        return is_array($stored) ? $stored : [];
    }

    public static function resolve(array $stored, string $externalId): array
    {
        $site = self::normalize(is_array($stored['site'] ?? null) ? $stored['site'] : []);
        $entry = $stored['forms'][$externalId] ?? [];
        $form = self::normalize(is_array($entry) ? $entry : [], true);
        $chosen = $form['mode'] === 'inherit' ? $site : $form;
        if ($chosen['mode'] === 'convaincre') {
            return array_replace(self::DEFAULTS, ['mode' => 'convaincre']);
        }
        return $chosen;
    }

    public static function forForm(string $externalId): array
    {
        return self::resolve(self::stored(), $externalId);
    }

    public static function contrast(string $first, string $second): float
    {
        $a = self::luminance($first);
        $b = self::luminance($second);
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    private static function luminance(string $hex): float
    {
        $rgb = array_map('hexdec', str_split(substr($hex, 1), 2));
        $linear = array_map(static function ($channel): float {
            $value = $channel / 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $rgb);
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    public static function css(array $raw): string
    {
        $style = self::normalize($raw);
        if ($style['mode'] === 'theme') {
            return '';
        }
        if ($style['mode'] === 'convaincre') {
            $style = self::DEFAULTS;
        }
        $buttonText = self::contrast($style['primary'], '#ffffff') >= self::contrast($style['primary'], '#000000') ? '#ffffff' : '#000000';
        $fieldText = self::contrast($style['field'], $style['text']) >= 4.5 ? $style['text']
            : (self::contrast($style['field'], '#ffffff') >= self::contrast($style['field'], '#000000') ? '#ffffff' : '#000000');
        return '--cc-primary:' . $style['primary']
            . ';--cc-button-text:' . $buttonText
            . ';--cc-bg:' . $style['background']
            . ';--cc-text:' . $style['text']
            . ';--cc-field:' . $style['field']
            . ';--cc-field-text:' . $fieldText
            . ';--cc-border:' . $style['border']
            . ';--cc-radius:' . $style['radius'] . 'px'
            . ';--cc-spacing:' . $style['spacing'] . 'px'
            . ';--cc-width:' . $style['width'] . 'px'
            . ';--cc-font:' . $style['font']
            . ';--cc-size:' . $style['size'] . 'px';
    }
}
