<?php

namespace App\Services\Isup;

use App\Models\Device;
use App\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Keeps the students (and their faces) on the terminals: the student's id is the terminal's employee number.
 */
class FaceSync
{
    public function __construct(private Gateway $gateway) {}

    /**
     * Write the student to every terminal of the branches they study in; remove them where they no longer belong.
     *
     * @return array<int, string|null> device id => error (null when written)
     */
    public function syncStudent(Student $student): array
    {
        $studying = $student->isStudying() && $student->face_photo !== null;
        $branchIds = $studying ? $student->faceBranchIds() : [];
        $results = [];

        foreach (Device::query()->where('is_active', true)->whereIn('branch_id', $branchIds)->get() as $device) {
            $results[$device->id] = $this->push($student, $device);
        }

        $this->removeWhereNotBelonging($student, $branchIds);

        return $results;
    }

    /**
     * Take the student off terminals of branches they no longer study in (or all, when they stopped studying).
     *
     * @param  list<int>|null  $branchIds  branches the student belongs to; computed when null
     */
    public function removeWhereNotBelonging(Student $student, ?array $branchIds = null): void
    {
        $branchIds ??= $student->isStudying() && $student->face_photo !== null ? $student->faceBranchIds() : [];

        $student->devices()->wherePivot('status', '!=', 'removed')->whereNotIn('devices.branch_id', $branchIds)->get()
            ->each(fn (Device $device) => $this->remove($student, $device));
    }

    /**
     * Add or update the student and the face on one terminal. Returns the error, or null when done.
     */
    public function push(Student $student, Device $device): ?string
    {
        try {
            $this->writeUser($student, $device);

            if ($student->face_photo !== null) {
                $this->writeFace($student, $device);
            }

            $this->remember($student, $device, 'synced');

            return null;
        } catch (Throwable $e) {
            Log::warning('ISUP: student not written to device', ['student' => $student->id, 'device' => $device->isup_id, 'error' => $e->getMessage()]);
            $this->remember($student, $device, 'failed', $e->getMessage());

            return $e->getMessage();
        }
    }

    public function remove(Student $student, Device $device): void
    {
        try {
            $this->gateway->json($device, 'PUT', '/ISAPI/AccessControl/UserInfo/Delete?format=json', [
                'UserInfoDelCond' => ['EmployeeNoList' => [['employeeNo' => (string) $student->id]]],
            ]);
            $this->remember($student, $device, 'removed');
        } catch (Throwable $e) {
            Log::warning('ISUP: student not removed from device', ['student' => $student->id, 'device' => $device->isup_id, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Students of the device's branch that should be on it but are not (new device, factory reset, earlier failure).
     * With $all every one of them is written again.
     */
    public function restoreDevice(Device $device, bool $all = false, int $limit = 200): int
    {
        $written = $device->students()->wherePivot('status', 'synced')->pluck('students.id')->all();
        $sent = 0;

        Student::query()
            ->whereNotNull('face_photo')
            ->whereHas('enrollments', fn ($q) => $q->whereIn('status', ['trial', 'active'])->whereHas('group', fn ($g) => $g->where('branch_id', $device->branch_id)))
            ->when(! $all, fn ($q) => $q->whereNotIn('id', $written))
            ->limit($limit)
            ->get()
            ->each(function (Student $student) use ($device, &$sent): void {
                $this->push($student, $device);
                $sent++;
            });

        return $sent;
    }

    /**
     * Set the terminal's clock to the server time, Tashkent zone, and keep it right with NTP.
     */
    public function fixClock(Device $device): void
    {
        $zone = (string) config('isup.time_zone');
        $now = now()->format('Y-m-d\TH:i:sP');
        $xml = fn (string $root, string $inner) => '<?xml version="1.0" encoding="UTF-8"?>'
            ."<{$root} version=\"2.0\" xmlns=\"http://www.isapi.org/ver20/XMLSchema\">{$inner}</{$root}>";

        $this->gateway->isapi($device, 'PUT', '/ISAPI/System/time', $xml('Time', "<timeMode>manual</timeMode><localTime>{$now}</localTime><timeZone>{$zone}</timeZone>"));

        try {
            $this->gateway->isapi($device, 'PUT', '/ISAPI/System/time/ntpServers/1', $xml('NTPServer', '<id>1</id><addressingFormatType>hostname</addressingFormatType><hostName>pool.ntp.org</hostName><portNo>123</portNo><synchronizeInterval>60</synchronizeInterval>'));
            $this->gateway->isapi($device, 'PUT', '/ISAPI/System/time', $xml('Time', "<timeMode>NTP</timeMode><timeZone>{$zone}</timeZone>"));
        } catch (Throwable) {
            // Manual time is already right; some models have no NTP settings.
        }
    }

    /**
     * Access events (face recognised) the terminal stored between the two moments.
     *
     * @return list<array<string, mixed>>
     */
    public function events(Device $device, CarbonInterface $from, CarbonInterface $to): array
    {
        $events = [];
        $position = 0;

        for ($page = 0; $page < 50; $page++) {
            $answer = $this->gateway->json($device, 'POST', '/ISAPI/AccessControl/AcsEvent?format=json', ['AcsEventCond' => [
                'searchID' => 'educrm-'.$device->id.'-'.Str::random(6),
                'searchResultPosition' => $position,
                'maxResults' => 30,
                'major' => 5,
                'minor' => 0,
                'startTime' => $from->format('Y-m-d\TH:i:sP'),
                'endTime' => $to->format('Y-m-d\TH:i:sP'),
            ]]);

            $list = $answer['AcsEvent']['InfoList'] ?? [];
            array_push($events, ...$list);
            $count = (int) ($answer['AcsEvent']['numOfMatches'] ?? count($list));
            $position += $count;

            if ($count === 0 || ($answer['AcsEvent']['responseStatusStrg'] ?? '') !== 'MORE') {
                break;
            }
        }

        return $events;
    }

    /**
     * Download link for the face photo, valid long enough for the terminal to fetch it.
     */
    public static function faceUrl(Student $student): string
    {
        $path = URL::temporarySignedRoute('isup.face', now()->addMinutes(30), ['student' => $student->id, 'v' => md5((string) $student->face_photo)], absolute: false);

        return rtrim((string) config('isup.face_base_url'), '/').$path;
    }

    private function writeUser(Student $student, Device $device): void
    {
        $user = ['UserInfo' => [
            'employeeNo' => (string) $student->id,
            'name' => Str::limit($student->full_name, 30, ''),
            'userType' => 'normal',
            'Valid' => ['enable' => true, 'beginTime' => '2020-01-01T00:00:00', 'endTime' => '2037-12-31T23:59:59', 'timeType' => 'local'],
            'doorRight' => '1',
            'RightPlan' => [['doorNo' => 1, 'planTemplateNo' => '1']],
        ]];

        try {
            $this->gateway->json($device, 'PUT', '/ISAPI/AccessControl/UserInfo/SetUp?format=json', $user);
        } catch (RuntimeException $e) {
            // Older firmware: add, or modify when the employee number is taken.
            try {
                $this->gateway->json($device, 'POST', '/ISAPI/AccessControl/UserInfo/Record?format=json', $user);
            } catch (RuntimeException) {
                $this->gateway->json($device, 'PUT', '/ISAPI/AccessControl/UserInfo/Modify?format=json', $user);
            }
        }
    }

    private function writeFace(Student $student, Device $device): void
    {
        $face = ['faceLibType' => 'blackFD', 'FDID' => '1', 'FPID' => (string) $student->id, 'faceURL' => self::faceUrl($student)];

        try {
            $this->gateway->json($device, 'PUT', '/ISAPI/Intelligent/FDLib/FDSetUp?format=json', $face, 30);
        } catch (RuntimeException) {
            $this->gateway->json($device, 'POST', '/ISAPI/Intelligent/FDLib/FaceDataRecord?format=json', $face, 30);
        }
    }

    private function remember(Student $student, Device $device, string $status, ?string $error = null): void
    {
        $student->devices()->syncWithoutDetaching([$device->id => [
            'status' => $status,
            'error' => $error === null ? null : Str::limit($error, 250),
            'synced_at' => now(),
        ]]);
    }
}
