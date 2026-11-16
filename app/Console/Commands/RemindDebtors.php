<?php

namespace App\Console\Commands;

use App\Models\Enrollment;
use App\Services\Notifications\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('debts:remind')]
#[Description('Send debt reminders to parents of students with a negative balance')]
class RemindDebtors extends Command
{
    public function handle(Notifier $notifier): int
    {
        $count = 0;

        Enrollment::query()->where('balance', '<', 0)->whereIn('status', ['active', 'trial'])
            ->with('student', 'group')->chunkById(100, function ($enrollments) use ($notifier, &$count) {
                foreach ($enrollments as $enrollment) {
                    $notifier->debtReminder($enrollment);
                    $count++;
                }
            });

        $this->line("Reminders: {$count}");

        return self::SUCCESS;
    }
}
