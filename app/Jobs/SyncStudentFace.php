<?php

namespace App\Jobs;

use App\Models\Student;
use App\Services\Isup\FaceSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Write the student (with the face) to the terminals of their branches, or remove them from the rest.
 */
class SyncStudentFace implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $studentId) {}

    public function handle(FaceSync $faces): void
    {
        $student = Student::query()->find($this->studentId);

        if ($student !== null) {
            $faces->syncStudent($student);
        }
    }
}
