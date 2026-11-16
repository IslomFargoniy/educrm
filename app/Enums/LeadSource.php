<?php

namespace App\Enums;

enum LeadSource: string
{
    case Instagram = 'instagram';
    case Telegram = 'telegram';
    case Facebook = 'facebook';
    case Referral = 'referral';
    case Banner = 'banner';
    case WalkIn = 'walk_in';
    case Website = 'website';
    case Other = 'other';
}
