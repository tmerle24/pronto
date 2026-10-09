<?php

namespace App\Support;

final class PhoneNumber
{
    /** German default: 0171 1234567 -> +491711234567. Null if not plausible. */
    public static function normalize(string $input): ?string
    {
        $number = preg_replace('/[\s\-\/().]/', '', $input) ?? '';
        if (str_starts_with($number, '00')) {
            $number = '+'.substr($number, 2);
        } elseif (str_starts_with($number, '0')) {
            $number = '+49'.substr($number, 1);
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $number) === 1 ? $number : null;
    }
}
