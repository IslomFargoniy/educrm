<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Student;
use App\Services\Isup\FaceSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Students who stopped studying leave the terminals; studying ones missing from a terminal are written to it.
 */
#[Signature('isup:reconcile')]
#[Description('Keep the students on the ISUP terminals in line with who is studying')]
class IsupReconcile extends Command
{
    public function handle(FaceSync $faces): int
    {
        Student::query()
            ->whereHas('devices', fn ($q) => $q->where('device_student.status', '!=', 'removed'))
            ->get()
            ->each(fn (Student $student) => $faces->removeWhereNotBelonging($student));

        Device::query()->where('is_active', true)->where('is_online', true)->get()
            ->each(fn (Device $device) => $faces->restoreDevice($device, limit: 50));

        return self::SUCCESS;
    }
}
