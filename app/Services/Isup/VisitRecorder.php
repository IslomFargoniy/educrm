<?php

namespace App\Services\Isup;

use App\Models\Device;
use App\Models\Student;
use App\Models\StudentVisit;
use App\Services\Notifications\Notifier;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Turns a terminal's access event into a student visit and tells the family.
 */
class VisitRecorder
{
    /** A live event whose device clock is further off than this uses the server time. */
    private const MAX_CLOCK_DRIFT_SECONDS = 120;

    /** Standing in front of the terminal repeats the pass every few seconds: one visit per this many seconds. */
    private const REPEAT_SECONDS = 120;

    /** Families hear only about passes this fresh (catch-up after an outage stays silent). */
    private const NOTIFY_WITHIN_MINUTES = 30;

    public function __construct(private Notifier $notifier) {}

    /**
     * @param  array<string, mixed>  $event  ISAPI AccessControllerEvent / AcsEvent item
     * @param  bool  $live  pushed by the device right now (not read back from its memory)
     */
    public function record(Device $device, array $event, ?string $dateTime, bool $live): ?StudentVisit
    {
        $employeeNo = trim((string) ($event['employeeNoString'] ?? $event['employeeNo'] ?? ''));
        $major = (int) ($event['majorEventType'] ?? $event['major'] ?? 5);

        if ($employeeNo === '' || $major !== 5) {
            return null;
        }

        $scannedAt = $this->time($dateTime ?? ($event['time'] ?? null), $live);
        $serial = isset($event['serialNo']) ? (string) $event['serialNo'] : null;
        // Only students the CRM wrote to this terminal: other people on it (or old numbers) never match a student.
        $student = ctype_digit($employeeNo)
            ? Student::query()->whereKey((int) $employeeNo)->whereHas('devices', fn ($q) => $q->whereKey($device->id)->where('device_student.status', 'synced'))->first()
            : null;

        if ($serial !== null && StudentVisit::query()->where('device_id', $device->id)->where('event_serial', $serial)->exists()) {
            return null;
        }

        $repeat = StudentVisit::query()
            ->where('device_id', $device->id)
            ->where('employee_no', $employeeNo)
            ->whereBetween('scanned_at', [$scannedAt->subSeconds(self::REPEAT_SECONDS), $scannedAt->addSeconds(self::REPEAT_SECONDS)])
            ->exists();

        if ($repeat) {
            return null;
        }

        try {
            $visit = StudentVisit::query()->create([
                'student_id' => $student?->id,
                'device_id' => $device->id,
                'branch_id' => $device->branch_id,
                'employee_no' => $employeeNo,
                'event_serial' => $serial,
                'direction' => $device->direction,
                'scanned_at' => $scannedAt,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $device->forceFill(['last_seen_at' => now(), 'is_online' => true])->save();

        if ($student !== null && $scannedAt->greaterThan(now()->subMinutes(self::NOTIFY_WITHIN_MINUTES))) {
            $this->notify($student, $visit, $device);
        }

        return $visit;
    }

    /**
     * First pass of the day: "arrived"; every later pass: "registered".
     */
    private function notify(Student $student, StudentVisit $visit, Device $device): void
    {
        $first = ! StudentVisit::query()
            ->where('student_id', $student->id)
            ->whereDate('scanned_at', $visit->scanned_at)
            ->where('scanned_at', '<', $visit->scanned_at)
            ->exists();

        $this->notifier->toFamily($student, $first ? 'arrived' : 'visit', [
            'time' => $visit->scanned_at->format('H:i'),
            'device' => $device->name,
        ], telegramOnly: true);
    }

    private function time(mixed $value, bool $live): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        try {
            $time = is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->setTimezone(config('app.timezone')) : $now;
        } catch (Throwable) {
            return $now;
        }

        return $live && abs($time->diffInSeconds($now)) > self::MAX_CLOCK_DRIFT_SECONDS ? $now : $time;
    }
}
