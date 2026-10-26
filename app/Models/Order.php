<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class Order extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled', 'returned'];

    // look-alike অক্ষর (I, O, 0, 1) বাদ দেওয়া
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    // Why an order came back: key => [label, counts toward the red flag?]
    // true  = the customer's side (refused, not reachable, fake ...)
    // false = not the customer's fault (damaged, wrong item ...): never counted against the number
    private const RETURN_REASONS = [
        'refused' => ['Customer refused to accept the parcel', true],
        'unreachable' => ['Customer not reachable / not at the address', true],
        'fake_order' => ['Fake order (customer says they never ordered)', true],
        'changed_mind' => ['Customer changed their mind', true],
        'damaged' => ['Product was damaged or defective', false],
        'wrong_item' => ['We sent the wrong item', false],
        'not_as_described' => ['Not as described / quality problem', false],
        'size_issue' => ['Size or fit problem', false],
        'courier_problem' => ['Lost or damaged by the courier', false],
        'other' => ['Other (see the note)', false],
    ];

    protected $fillable = [
        'code', 'customer_name', 'phone', 'email', 'address',
        'delivery_zone_name', 'subtotal', 'discount', 'delivery_charge', 'total', 'status',
        'return_reason', 'return_note', 'returned_at',
        'source', 'created_by_admin', 'ip_address', 'stock_deducted',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'delivery_charge' => 'decimal:2',
            'total' => 'decimal:2',
            'returned_at' => 'datetime',
            'created_by_admin' => 'boolean',
            'stock_deducted' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    // Returned orders of the same mobile number that count toward the red flag
    // (a returned order with no recorded reason counts too: the reason is unknown)
    public function phoneReturns(): HasMany
    {
        return $this->hasMany(Order::class, 'phone', 'phone')
            ->where('status', 'returned')
            ->where(fn ($query) => $query->whereNull('return_reason')->orWhereIn('return_reason', self::countedReturnReasons()));
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
            'returned' => 'Returned',
        ];
    }

    // Statuses an admin can pick when creating an order in the Order desk
    public static function deskStatusOptions(): array
    {
        return Arr::except(self::statusOptions(), ['cancelled', 'returned']);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            'pending' => 'warning',
            'confirmed' => 'info',
            'shipped' => 'primary',
            'delivered' => 'success',
            'cancelled' => 'danger',
            'returned' => 'gray',
            default => 'gray',
        };
    }

    // ---------- return reasons ----------

    // [key => label]
    public static function returnReasonOptions(): array
    {
        return array_map(fn (array $reason): string => $reason[0], self::RETURN_REASONS);
    }

    // Grouped for the drop-down: the customer's side first, then "not the customer's fault"
    public static function returnReasonGroups(): array
    {
        $counted = [];
        $notCounted = [];

        foreach (self::RETURN_REASONS as $key => [$label, $counts]) {
            if ($counts) {
                $counted[$key] = $label;
            } else {
                $notCounted[$key] = $label;
            }
        }

        return [
            'Customer problem (counts toward the red flag)' => $counted,
            "Not the customer's fault (does not count)" => $notCounted,
        ];
    }

    // Keys of the reasons that count toward the red flag
    public static function countedReturnReasons(): array
    {
        return array_keys(array_filter(self::RETURN_REASONS, fn (array $reason): bool => $reason[1]));
    }

    public static function returnReasonLabel(?string $reason): string
    {
        return self::RETURN_REASONS[$reason][0] ?? 'Not recorded';
    }

    // No reason recorded (old orders) = counts, because we do not know
    public static function returnReasonCounts(?string $reason): bool
    {
        return self::RETURN_REASONS[$reason][1] ?? true;
    }

    // ---------- where the order came from ----------

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