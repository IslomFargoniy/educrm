<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A Hikvision face terminal connected over ISUP 5.0 (entrance or exit of a branch).
 *
 * @property-read int|null $synced_count
 * @property-read int|null $failed_count
 * @property-read DeviceStudent $sync
 */
#[Fillable(['branch_id', 'name', 'direction', 'isup_id', 'isup_key', 'is_active', 'is_online', 'ip', 'serial', 'last_seen_at', 'events_synced_at'])]
#[Hidden(['isup_key'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'isup_key' => 'encrypted',
            'is_active' => 'boolean',
            'is_online' => 'boolean',
            'last_seen_at' => 'datetime',
            'events_synced_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Device $device): void {
            $device->isup_id ??= self::nextIsupId();
            $device->isup_key ??= self::newKey();
        });
    }

    /**
     * Short Device ID typed into the terminal: edu1, edu2, ...
     */
    public static function nextIsupId(): string
    {
        $number = (int) self::query()->max('id') + 1;

        while (self::query()->where('isup_id', 'edu'.$number)->exists()) {
            $number++;
        }

        return 'edu'.$number;
    }

    /**
     * ISUP key typed into the terminal: four letters and four digits (e.g. kmtr4827), nothing to confuse.
     */
    public static function newKey(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyz';
        $key = '';

        for ($i = 0; $i < 4; $i++) {
            $key .= $letters[random_int(0, strlen($letters) - 1)];
        }

        return $key.random_int(1000, 9999);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Students written to the device, with the result of the last attempt.
     *
     * @return BelongsToMany<Student, $this, DeviceStudent, 'sync'>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class)->using(DeviceStudent::class)->as('sync')->withPivot('status', 'error', 'synced_at')->withTimestamps();
    }
}
