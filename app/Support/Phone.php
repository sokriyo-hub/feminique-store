<?php

namespace App\Support;

class Phone
{
    // "+880 1712-345678", "8801712345678", "1712345678", "০১৭১২৩৪৫৬৭৮" -> "01712345678" (ভুল হলে null)
    public static function normalize(string $input): ?string
    {
        $value = strtr($input, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '880')) {
            $digits = substr($digits, 2);
        }

        if (preg_match('/^1[3-9][0-9]{8}$/', $digits)) {
            $digits = '0' . $digits;
        }

        return preg_match('/^01[3-9][0-9]{8}$/', $digits) ? $digits : null;
    }
}