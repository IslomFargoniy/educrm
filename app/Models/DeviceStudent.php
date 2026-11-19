<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Whether a student (and face) is on a device: synced, failed or removed.
 */
class DeviceStudent extends Pivot
{
    public $incrementing = true;

    protected $table = 'device_student';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }
}
