<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class Order extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];

    // look-alike অক্ষর (I, O, 0, 1) বাদ দেওয়া
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'code', 'customer_name', 'phone', 'email', 'address',
        'delivery_zone_name', 'subtotal', 'discount', 'delivery_charge', 'total', 'status',
        'source', 'created_by_admin', 'stock_deducted',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'total' => 'decimal:2',
            'created_by_admin' => 'boolean',
            'stock_deducted' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // নতুন unique order code: FQ- + ৮ অক্ষর (random, অনুমান করা যায় না)
    public static function generateCode(): string
    {
        do {
            $suffix = '';
            for ($i = 0; $i < 8; $i++) {
                $suffix .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
            $code = 'FQ-' . $suffix;
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'confirmed' => 'Confirmed',
            'shipped' => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];
    }

    // Statuses an admin can pick when creating an order in the Order desk
    public static function deskStatusOptions(): array
    {
        return Arr::except(self::statusOptions(), ['cancelled']);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'confirmed' => 'info',
            'shipped' => 'primary',
            'delivered' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    // Where the order came from
    public static function sourceOptions(): array
    {
        return [
            'website' => 'Website',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'whatsapp' => 'WhatsApp',
            'phone' => 'Phone call',
            'walk_in' => 'Walk-in',
            'other' => 'Other',
        ];
    }

    // Sources an admin can pick in the Order desk (website orders come from customers only)
    public static function deskSourceOptions(): array
    {
        return Arr::except(self::sourceOptions(), ['website']);
    }

    public static function sourceColor(string $source): string
    {
        return match ($source) {
            'website' => 'gray',
            'facebook' => 'info',
            'instagram' => 'danger',
            'whatsapp' => 'success',
            'phone' => 'warning',
            'walk_in' => 'primary',
            default => 'gray',
        };
    }
}