<?php

namespace App\Events;

use App\Models\Enrollment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * The student's balance in a group went below zero.
 */
class DebtStarted
{
    use Dispatchable, SerializesModels;

    public function __construct(public Enrollment $enrollment) {}
}
