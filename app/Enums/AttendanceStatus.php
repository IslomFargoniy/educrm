<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Absent = 'absent';
    case Excused = 'excused';

    /**
     * Whether the lesson is billed for this status.
     */
    public function isBillable(): bool
    {
        return $this !== self::Excused;
    }
}
