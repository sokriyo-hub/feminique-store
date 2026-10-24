<?php

namespace App\Models;

use App\Support\AdSlots;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Campaign extends Model
{
    protected $fillable = [
        'name', 'discount_percent', 'starts_at', 'ends_at', 'is_active', 'all_products',
        'free_delivery', 'show_banner', 'show_countdown', 'banner_slots',
        'banner_title', 'banner_text', 'banner_button', 'banner_image',
    ];

    protected function casts(): array
    {
        return [
            'discount_percent' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'all_products' => 'boolean',
            'free_delivery' => 'boolean',
            'show_banner' => 'boolean',
            'show_countdown' => 'boolean',
            'banner_slots' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // A replaced banner image is deleted from disk
        static::updating(function (Campaign $campaign) {
            if ($campaign->isDirty('banner_image') && $campaign->getOriginal('banner_image')) {
                Storage::disk('public')->delete($campaign->getOriginal('banner_image'));
            }
        });

        static::deleting(function (Campaign $campaign) {
            if ($campaign->banner_image) {
                Storage::disk('public')->delete($campaign->banner_image);
            }
        });
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'campaign_category');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'campaign_product');
    }

    // The places where the banner is shown (no list saved = the default place)
    public function bannerSlots(): array
    {
        $slots = array_values(array_filter((array) $this->banner_slots, 'is_string'));

        return $slots ?: [AdSlots::DEFAULT_CAMPAIGN_SLOT];
    }

    // Campaigns that are on right now
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    // running | scheduled | ended | off
    public function status(): string
    {
        if (! $this->is_active) {
            return 'off';
        }

        if (now()->lt($this->starts_at)) {
            return 'scheduled';
        }

        if (now()->gt($this->ends_at)) {
            return 'ended';
        }

        return 'running';
    }
}