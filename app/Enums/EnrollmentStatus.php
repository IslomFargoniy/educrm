<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Transferred = 'transferred';
    case Left = 'left';

    /**
     * Whether the student still attends lessons with this status.
     */
    public function isStudying(): bool
    {
        return $this === self::Trial || $this === self::Active;
    }
}
