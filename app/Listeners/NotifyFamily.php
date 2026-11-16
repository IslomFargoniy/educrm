<?php

namespace App\Listeners;

use App\Events\DebtStarted;
use App\Events\PaymentReceived;
use App\Events\StudentAbsent;
use App\Services\Notifications\Notifier;

class NotifyFamily
{
    public function __construct(private Notifier $notifier) {}

    public function handlePayment(PaymentReceived $event): void
    {
        $enrollment = $event->payment->enrollment()->with('student', 'group')->first();

        $this->notifier->toFamily($enrollment->student, 'payment', [
            'group' => $enrollment->group->name,
            'amount' => number_format($event->payment->amount, 0, '.', ' '),
            'balance' => number_format($enrollment->balance, 0, '.', ' '),
        ]);
    }

    public function handleAbsence(StudentAbsent $event): void
    {
        $attendance = $event->attendance->loadMissing('enrollment.student', 'enrollment.group', 'lesson');

        $this->notifier->toFamily($attendance->enrollment->student, 'absent', [
            'group' => $attendance->enrollment->group->name,
            'date' => $attendance->lesson->date->format('d.m.Y'),
        ]);
    }

    public function handleDebt(DebtStarted $event): void
    {
        $this->notifier->debtReminder($event->enrollment);
    }
}
