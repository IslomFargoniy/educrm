<?php

namespace App\Enums;

enum ChargeStatus: string
{
    case PendingTrial = 'pending_trial';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
}
