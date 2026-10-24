<?php

namespace App\Models;

use App\Support\AdSlots;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class Ad extends Model
{
    private const FILE_COLUMNS = ['media', 'poster', 'mobile_media'];

    protected $fillable = [
        'name', 'media', 'poster', 'mobile_media',
        'title', 'text', 'button_text', 'link_url', 'text_color', 'text_align',
        'placements', 'is_active', 'starts_at', 'ends_at',
    ];

    private static ?Collection $liveCache = null;

    protected function casts(): array
    {
        return [
            'placements' => 'array',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // A replaced picture/video is deleted from disk
        static::updating(function (Ad $ad) {
            foreach (self::FILE_COLUMNS as $column) {
                if ($ad->isDirty($column) && $ad->getOriginal($column)) {
                    Storage::disk('public')->delete($ad->getOriginal($column));
                }
            }
        });

        static::deleting(function (Ad $ad) {
            foreach (self::FILE_COLUMNS as $column) {
                if ($ad->{$column}) {
                    Storage::disk('public')->delete($ad->{$column});
                }
            }
        });
    }

    // Ads that may be shown right now (switched on and inside their dates)
    public function scopeLive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    // The ad for one place (null = none). A place normally has ONE ad (the forms make sure of it);
    // if an old record still shares a place, the oldest one is shown.
    public static function liveFor(string $slot): ?Ad
    {
        self::$liveCache ??= static::live()->orderBy('id')->get();

        return self::$liveCache->first(fn (Ad $ad): bool => in_array($slot, (array) $ad->placements, true));
    }

    public static function flushCache(): void
    {
        self::$liveCache = null;
    }

    // Places of this ad that another ad or banner also uses (empty = no problem)
    public function conflicts(): array
    {
        if (! in_array($this->status(), ['live', 'scheduled'], true)) {
            return [];
        }

        return array_values(array_intersect(
            (array) $this->placements,
            array_keys(AdSlots::taken('ad', $this->id))
        ));
    }

    // live | scheduled | ended | off
    public function status(): string
    {
        if (! $this->is_active) {
            return 'off';
        }

        if ($this->starts_at && now()->lt($this->starts_at)) {
            return 'scheduled';
        }

        if ($this->ends_at && now()->gt($this->ends_at)) {
            return 'ended';
        }

        return 'live';
    }

    // image | gif | video (from the stored file name)
    public function kind(): string
    {
        return match (strtolower(pathinfo((string) $this->media, PATHINFO_EXTENSION))) {
            'mp4' => 'video',
            'gif' => 'gif',
            default => 'image',
        };
    }

    // Small picture for the admin list
    public function thumb(): ?string
    {
        return $this->kind() === 'video' ? $this->poster : $this->media;
    }

    // Only https://, http:// or a path of this site (/category/rings). Anything else = no link.
    public static function isSafeLink(string $link): bool
    {
        return (bool) preg_match('#^(https?://\S+|/(?!/)\S*)$#i', $link);
    }

    public function safeLink(): ?string
    {
        $link = trim((string) $this->link_url);

        return $link !== '' && self::isSafeLink($link) ? $link : null;
    }
}