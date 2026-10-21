<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    // Category -> Sub -> Sub-sub (change this number to allow more levels)
    public const MAX_DEPTH = 3;

    protected $fillable = ['name', 'slug', 'image', 'parent_id', 'sort_order', 'show_in_navbar'];

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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    // Sub-categories are always A-Z
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('name');
    }

    public function scopeTopLevel(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    // 1 = top level, 2 = sub, 3 = sub-sub
    public function depth(): int
    {
        $depth = 1;
        $node = $this;

        while ($node->parent_id !== null && $depth < 20) {
            $node = $node->parent()->first();

            if (! $node) {
                break;
            }

            $depth++;
        }

        return $depth;
    }

    // "Rings > Gold Rings" (used in admin lists and CSV)
    public function path(): string
    {
        $names = [$this->name];
        $node = $this;
        $guard = 0;

        while ($node->parent_id !== null && $guard++ < 20) {
            $node = $node->parent()->first();

            if (! $node) {
                break;
            }

            array_unshift($names, $node->name);
        }

        return implode(' > ', $names);
    }

    // ids of every category below this one (children, grandchildren ...), without itself
    public function descendantIds(): array
    {
        $all = [];
        $current = [$this->id];
        $guard = 0;

        while ($current && $guard++ < 20) {
            $next = static::whereIn('parent_id', $current)->pluck('id')->all();
            $all = array_merge($all, $next);
            $current = $next;
        }

        return $all;
    }

    // this category + everything below it (for listing the products of a whole branch)
    public function branchIds(): array
    {
        return array_merge([$this->id], $this->descendantIds());
    }
}