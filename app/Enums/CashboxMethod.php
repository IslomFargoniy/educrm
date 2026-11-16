<?php

namespace App\Enums;

enum CashboxMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Click = 'click';
    case Payme = 'payme';
    case Bank = 'bank';
}
