<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TrackingController
{
    private const IP_MAX_FAILS = 5;
    private const IP_DECAY = 600;      // 10 minutes
    private const PHONE_MAX_FAILS = 10;
    private const PHONE_DECAY = 3600;  // 1 hour

    public function form(): View
    {
        return view('track', ['order' => null, 'error' => null]);
    }

    public function lookup(Request $request): View
    {
        $phone = Phone::normalize((string) $request->input('phone'));
        $code = self::normalizeCode((string) $request->input('code'));

        $ipKey = 'track-ip:' . $request->ip();
        $phoneKey = $phone ? 'track-phone:' . $phone : null;

        if (
            RateLimiter::tooManyAttempts($ipKey, self::IP_MAX_FAILS)
            || ($phoneKey && RateLimiter::tooManyAttempts($phoneKey, self::PHONE_MAX_FAILS))
        ) {
            return view('track', [
                'order' => null,
                'error' => __('Too many attempts. Please try again in a few minutes.'),
            ]);
        }

        $order = null;

        if ($phone && $code) {
            $order = Order::with('items')
                ->where('code', $code)
                ->where('phone', $phone)
                ->first();
        }

        if (! $order) {
            RateLimiter::hit($ipKey, self::IP_DECAY);

            if ($phoneKey) {
                RateLimiter::hit($phoneKey, self::PHONE_DECAY);
            }

            return view('track', [
                'order' => null,
                'error' => __('We could not find an order with these details. Please check your mobile number and order code.'),
            ]);
        }

        return view('track', ['order' => $order, 'error' => null]);
    }

    // "fq-abcd2345", "FQABCD2345", "abcd2345" -> "FQ-ABCD2345" (invalid -> null)
    private static function normalizeCode(string $input): ?string
    {
        $value = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input) ?? '');

        if (strlen($value) === 8) {
            $value = 'FQ' . $value;
        }

        if (! preg_match('/^FQ[A-HJ-NP-Z2-9]{8}$/', $value)) {
            return null;
        }

        return 'FQ-' . substr($value, 2);
    }
}