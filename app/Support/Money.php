<?php

namespace App\Support;

class Money
{
    // 1200 -> "Tk 1,200", 99.5 -> "Tk 99.50"
    public static function format(float|int|string $amount): string
    {
        $formatted = number_format((float) $amount, 2, '.', ',');
        $formatted = preg_replace('/\.00$/', '', $formatted);

        return 'Tk ' . $formatted;
    }
}