<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Services\LessonScheduler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('lessons:generate')]
#[Description('Create planned lessons for the current and next month')]
class GenerateLessons extends Command
{
    public function handle(LessonScheduler $scheduler): int
    {
        $created = Group::query()->where('status', 'active')->get()
            ->sum(fn (Group $group) => $scheduler->generateUpcoming($group));
        $this->line("Created lessons: {$created}");

        return self::SUCCESS;
    }
}
