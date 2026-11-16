<?php

namespace App\Support;

/**
 * Normalizes Uzbek phone numbers to the +998XXXXXXXXX format.
 */
final class Phone
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }

        return '+'.$digits;
    }

    public static function isValid(?string $phone): bool
    {
        return $phone !== null && preg_match('/^\+998\d{9}$/', $phone) === 1;
    }
}
