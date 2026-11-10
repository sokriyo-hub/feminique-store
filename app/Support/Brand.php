<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * The brand of the shop, all editable in admin Settings: name, tagline, colors.
 * Colors are written into the page as CSS variables, so a change shows at once (no rebuild).
 */
class Brand
{
    public const DEFAULT_NAME = 'HeshelMart';

    public const DEFAULT_TAGLINE = 'Good Food • Better Living';

    // Settings key => default color (Brand Kit v1.0)
    public const COLORS = [
        'brand_color_primary' => '#07502F',
        'brand_color_accent' => '#C5A46D',
        'brand_color_text' => '#252A27',
        'brand_color_soft' => '#E7EEE8',
        'brand_color_ivory' => '#F8F5EC',
        'brand_color_sand' => '#EFE5D3',
        'brand_color_danger' => '#B42318',
    ];

    private static ?array $all = null;

    // Forget what was loaded (used after saving and in tests)
    public static function flush(): void
    {
        self::$all = null;
    }

    private static function all(): array
    {
        if (self::$all === null) {
            try {
                self::$all = Setting::pluck('value', 'name')->all();
            } catch (Throwable) {
                // The database is not ready (for example while an error page is shown): use the defaults
                self::$all = [];
            }
        }

        return self::$all;
    }

    public static function name(): string
    {
        $name = trim((string) (self::all()['brand_name'] ?? ''));

        return $name !== '' ? $name : self::DEFAULT_NAME;
    }

    // Never saved: the default. Saved as empty: empty (nothing is shown).
    public static function tagline(): string
    {
        $all = self::all();

        return array_key_exists('brand_tagline', $all)
            ? trim((string) $all['brand_tagline'])
            : self::DEFAULT_TAGLINE;
    }

    // Short text for the browser / search engines
    public static function description(): string
    {
        $tagline = self::tagline();

        return $tagline !== '' ? self::name() . ' - ' . $tagline : self::name();
    }

    // One color: the saved one if it is a clean #RRGGBB, otherwise the brand default
    public static function color(string $key): string
    {
        $value = self::all()[$key] ?? null;

        if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', trim($value))) {
            return strtoupper(trim($value));
        }

        return self::COLORS[$key];
    }

    // The same color, 25% darker (button hover, dark green)
    public static function darken(string $hex): string
    {
        $parts = sscanf($hex, '#%02x%02x%02x');

        return sprintf('#%02X%02X%02X',
            (int) round($parts[0] * 0.75),
            (int) round($parts[1] * 0.75),
            (int) round($parts[2] * 0.75),
        );
    }

    // <style> that overrides the theme colors. Empty when every color is still the brand default.
    public static function styleTag(): HtmlString
    {
        $colors = [];
        $custom = false;

        foreach (self::COLORS as $key => $default) {
            $colors[$key] = self::color($key);

            if ($colors[$key] !== $default) {
                $custom = true;
            }
        }

        if (! $custom) {
            return new HtmlString('');
        }

        $dark = self::darken($colors['brand_color_primary']);

        $variables = [
            '--color-forest' => $colors['brand_color_primary'],
            '--color-cocoa' => $colors['brand_color_primary'],
            '--color-forest-dark' => $dark,
            '--color-mocha' => $dark,
            '--color-gold' => $colors['brand_color_accent'],
            '--color-taupe' => $colors['brand_color_accent'],
            '--color-charcoal' => $colors['brand_color_text'],
            '--color-sage' => $colors['brand_color_soft'],
            '--color-beige' => $colors['brand_color_soft'],
            '--color-ivory' => $colors['brand_color_ivory'],
            '--color-sand' => $colors['brand_color_sand'],
            '--color-danger' => $colors['brand_color_danger'],
        ];

        $css = '';

        foreach ($variables as $name => $value) {
            $css .= $name . ': ' . $value . '; ';
        }

        return new HtmlString('<style>:root { ' . trim($css) . ' }</style>');
    }
}