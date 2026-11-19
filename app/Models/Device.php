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
            // Terminals refuse a digits-only key; the Device ID (letters and digits) is easy to type twice.
            $device->isup_key ??= (string) config('isup.default_key') ?: self::keyFor($device->isup_id);
        });
    }

    /**
     * Device ID typed into the terminal: the CRM domain without dots plus a number (crmmensalcuz1); terminals take letters and digits only.
     */
    public static function nextIsupId(): string
    {
        $prefix = (string) config('isup.device_id_prefix');
        $number = (int) self::query()->max('id') + 1;

        while (self::query()->where('isup_id', substr($prefix, 0, 32 - strlen((string) $number)).$number)->exists()) {
            $number++;
        }

        return substr($prefix, 0, 32 - strlen((string) $number)).$number;
    }

    /**
     * Key made from the Device ID: terminals take 8 to 16 characters.
     */
    public static function keyFor(string $isupId): string
    {
        return str_pad(substr($isupId, -16), 8, '0', STR_PAD_LEFT);
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
