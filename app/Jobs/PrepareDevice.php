<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\Isup\FaceSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * After a terminal connects: set its clock and write the students it is missing (or, on request, all of them).
 */
class PrepareDevice implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public int $deviceId, public bool $all = false) {}

    public function uniqueId(): string
    {
        return $this->deviceId.($this->all ? '-all' : '');
    }

    public function handle(FaceSync $faces): void
    {
        $device = Device::query()->find($this->deviceId);

        if ($device === null || ! $device->is_active) {
            return;
        }

        try {
            $faces->fixClock($device);
        } catch (Throwable) {
            // The device may still be finishing its login; students are written anyway.
        }

        $faces->restoreDevice($device, $this->all, $this->all ? 2000 : 200);
    }
}
