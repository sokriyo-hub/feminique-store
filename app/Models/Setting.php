<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['name', 'value'];

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