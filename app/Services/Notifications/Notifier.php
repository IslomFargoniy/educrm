<?php

namespace App\Services\Notifications;

use App\Jobs\SendNotification;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;

/**
 * Sends messages to the parents (and the student's own account) of a student.
 */
class Notifier
{
    /**
     * @param  array<string, scalar>  $params
     * @param  bool  $telegramOnly  frequent messages (terminal passes) never fall back to paid SMS
     */
    public function toFamily(Student $student, string $template, array $params, bool $telegramOnly = false): void
    {
        $recipients = $student->guardians()->get();

        if ($student->user_id !== null && ($own = User::query()->find($student->user_id))) {
            $recipients->push($own);
        }

        foreach ($recipients->unique('id') as $user) {
            SendNotification::dispatch($user->id, $template, ['student' => $student->full_name, ...$params], $telegramOnly);
        }
    }

    public function debtReminder(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('student', 'group');

        $this->toFamily($enrollment->student, 'debt', [
            'group' => $enrollment->group->name,
            'amount' => number_format(-$enrollment->balance, 0, '.', ' '),
        ]);
    }
}
