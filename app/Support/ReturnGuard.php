<?php

namespace App\Support;

use App\Models\BlockedPhone;
use App\Models\Order;
use App\Models\Setting;

/**
 * Fake / refused Cash-on-Delivery orders. Everything here goes by the MOBILE NUMBER
 * (many people can share one IP on mobile internet, so an IP is never used to block anyone).
 * Only returns that are the customer's side count: a damaged product is never held against a number.
 */
class ReturnGuard
{
    public const DEFAULT_LIMIT = 3;

    // Red flag after this many counted returns from one number (Settings). 1, 2, 3 ...
    public static function limit(): int
    {
        $value = (int) Setting::get('return_flag_limit', (string) self::DEFAULT_LIMIT);

        return $value >= 1 ? $value : self::DEFAULT_LIMIT;
    }

    // Returned orders that count (customer's side, or no reason recorded)
    public static function returnCount(string $phone): int
    {
        return Order::where('phone', $phone)
            ->where('status', 'returned')
            ->where(fn ($query) => $query->whereNull('return_reason')->orWhereIn('return_reason', Order::countedReturnReasons()))
            ->count();
    }

    public static function isFlagged(string $phone): bool
    {
        return self::returnCount($phone) >= self::limit();
    }

    // Numbers for the history page of one mobile number
    public static function history(string $phone): array
    {
        $orders = Order::where('phone', $phone)->get(['status', 'return_reason', 'total']);
        $returned = $orders->where('status', 'returned');
        $counted = $returned->filter(fn (Order $order): bool => Order::returnReasonCounts($order->return_reason));

        return [
            'orders' => $orders->count(),
            'delivered' => $orders->where('status', 'delivered')->count(),
            'cancelled' => $orders->where('status', 'cancelled')->count(),
            'returned' => $returned->count(),
            'counted' => $counted->count(),
            'not_counted' => $returned->count() - $counted->count(),
            'counted_value' => (float) $counted->sum('total'),
        ];
    }

    public static function blockedRecord(string $phone): ?BlockedPhone
    {
        return BlockedPhone::where('phone', $phone)->first();
    }

    public static function block(string $phone, ?string $reason = null): BlockedPhone
    {
        return BlockedPhone::firstOrCreate(['phone' => $phone], ['reason' => filled($reason) ? trim($reason) : null]);
    }

    public static function unblock(string $phone): void
    {
        BlockedPhone::where('phone', $phone)->delete();
    }

    // Website checkout: the message for a blocked number (null = allowed). The reason is never shown.
    public static function checkoutMessage(string $phone): ?string
    {
        return self::blockedRecord($phone) !== null
            ? __('Sorry, we cannot take online orders from this mobile number right now. Please contact us.')
            : null;
    }

    // Order desk: a red warning for the admin (never blocks). null = nothing to say.
    public static function warningFor(string $rawPhone): ?string
    {
        $phone = Phone::normalize($rawPhone);

        if ($phone === null) {
            return null;
        }

        $blocked = self::blockedRecord($phone);

        if ($blocked !== null) {
            $reason = filled($blocked->reason) ? ' Reason: ' . $blocked->reason . '.' : '';

            return 'This number is BLOCKED: it cannot order on the website.' . $reason . ' You can still create this order.';
        }

        $count = self::returnCount($phone);

        if ($count >= self::limit()) {
            return 'Warning: this number has ' . $count . ' returned order(s) (red flag from ' . self::limit() . '). Think before you send goods with Cash on Delivery.';
        }

        return null;
    }
}