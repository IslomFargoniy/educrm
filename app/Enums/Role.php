<?php

namespace App\Enums;

enum Role: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';
    case Manager = 'manager';
    case Teacher = 'teacher';
    case Parent = 'parent';
    case Student = 'student';

    /**
     * Roles that work inside the center (staff), as opposed to portal users.
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Superadmin, self::Admin, self::Manager, self::Teacher];
    }
}
