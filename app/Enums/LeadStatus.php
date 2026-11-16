<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Trial = 'trial';
    case Converted = 'converted';
    case Rejected = 'rejected';
}
