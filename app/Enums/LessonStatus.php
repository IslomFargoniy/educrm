<?php

namespace App\Enums;

enum LessonStatus: string
{
    case Planned = 'planned';
    case Held = 'held';
    case Cancelled = 'cancelled';
}
