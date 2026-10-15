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
}