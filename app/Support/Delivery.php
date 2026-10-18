<?php

namespace App\Support;

use App\Models\DeliveryZone;
use App\Models\Setting;

class Delivery
{
    // Settings-এর "Free delivery above" মান (খালি বা ০ হলে সুবিধা বন্ধ)
    public static function freeThreshold(): ?float
    {
        $value = Setting::get('free_delivery_threshold');

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    // একটা zone-এর জন্য আসল delivery charge (free-delivery ধরে)
    public static function charge(DeliveryZone $zone, float $subtotal): float
    {
        $threshold = self::freeThreshold();

        if ($threshold !== null && $subtotal >= $threshold) {
            return 0.0;
        }

        return (float) $zone->charge;
    }
}