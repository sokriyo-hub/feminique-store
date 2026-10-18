<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'image', 'sort_order', 'show_in_navbar'];

    protected function casts(): array
    {
        return [
            'show_in_navbar' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // ছবি বদলালে পুরোনো ফাইল মুছে যাবে
        static::updating(function (Category $category) {
            if ($category->isDirty('image') && $category->getOriginal('image')) {
                Storage::disk('public')->delete($category->getOriginal('image'));
            }
        });

        // category মুছলে তার ছবিও মুছে যাবে
        static::deleting(function (Category $category) {
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}