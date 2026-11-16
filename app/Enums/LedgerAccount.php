<?php

namespace App\Enums;

enum LedgerAccount: string
{
    case Cashbox = 'cashbox';
    case Teacher = 'teacher';
    case Enrollment = 'enrollment';
}
