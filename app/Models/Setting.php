<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['name', 'value'];

    // Hero text shown until the admin saves their own (saved as empty = that part is hidden)
    public const HERO_DEFAULTS = [
        'hero_eyebrow' => 'Good Food • Better Living',
        'hero_title' => "Thoughtful picks for\nyour kitchen and home",
        'hero_text' => "Everyday kitchen and lifestyle products,\ncarefully chosen and easy to order.",
        'hero_badge_1' => 'Carefully chosen products',
        'hero_badge_2' => 'Fast & reliable delivery',
        'hero_badge_3' => 'Cash on Delivery',
    ];

    // একটা setting-এর মান পড়া: Setting::get('footer_phone', 'ডিফল্ট')
    public static function get(string $name, ?string $default = null): ?string
    {
        return static::where('name', $name)->value('value') ?? $default;
    }

    // একটা setting সেভ করা: Setting::put('footer_phone', '01700000000')
    public static function put(string $name, ?string $value): void
    {
        static::updateOrCreate(['name' => $name], ['value' => $value]);
    }

    // Never saved: the default text. Saved (even empty): the saved value.
    public static function valueOrDefault(string $name): ?string
    {
        $row = static::where('name', $name)->first();

        return $row ? $row->value : (self::HERO_DEFAULTS[$name] ?? null);
    }

    // All hero texts for the storefront
    public static function heroContent(): array
    {
        $stored = static::whereIn('name', array_keys(self::HERO_DEFAULTS))->pluck('value', 'name');

        $content = [];

        foreach (self::HERO_DEFAULTS as $key => $default) {
            $content[$key] = $stored->has($key) ? trim((string) $stored[$key]) : $default;
        }

        return $content;
    }

    // hero ছবির তালিকা (ক্রম অনুযায়ী)। নতুন তালিকা না থাকলে পুরোনো একক ছবি ধরা হয়।
    public static function heroImages(): array
    {
        $list = json_decode((string) static::get('hero_images'), true);

        if (is_array($list) && $list) {
            return array_values(array_filter($list, 'is_string'));
        }

        $single = static::get('hero_image');

        return $single ? [$single] : [];
    }
}