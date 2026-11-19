<?php

namespace App\Support;

/**
 * First name + last name are entered separately and shown as one full name ("Ism Familiya").
 */
final class PersonName
{
    public static function join(?string $firstName, ?string $lastName): string
    {
        return trim(self::clean($firstName).' '.self::clean($lastName));
    }

    /**
     * @return array{0: string, 1: ?string} first name, last name
     */
    public static function split(?string $fullName): array
    {
        $parts = explode(' ', self::clean($fullName), 2);

        return [$parts[0], isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null];
    }

    private static function clean(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
