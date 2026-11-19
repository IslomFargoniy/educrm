<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Isup\FaceSync;
use App\Services\Isup\Gateway;
use App\Services\Isup\VisitRecorder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Read back passes the terminals stored, so nothing is lost when a live event did not arrive.
 */
#[Signature('isup:sync-events {--hours=24 : How far back to read on the first run}')]
#[Description('Read access events from the ISUP terminals and record student visits')]
class IsupSyncEvents extends Command
{
    public function handle(Gateway $gateway, FaceSync $faces, VisitRecorder $visits): int
    {
        $online = $gateway->devices();

        if ($online === null) {
            return self::SUCCESS;
        }

        foreach (Device::query()->where('is_active', true)->get() as $device) {
            $isOnline = (bool) ($online[$device->isup_id]['online'] ?? false);

            if ($device->is_online !== $isOnline) {
                $device->forceFill(['is_online' => $isOnline])->save();
            }

            if (! $isOnline) {
                continue;
            }

            $from = $device->events_synced_at?->subMinutes(10) ?? now()->subHours((int) $this->option('hours'));
            $to = now()->addMinutes(5);

            try {
                $recorded = 0;
                foreach ($faces->events($device, $from, $to) as $event) {
                    $recorded += $visits->record($device, $event, $event['time'] ?? null, live: false) ? 1 : 0;
                }
                $device->forceFill(['events_synced_at' => now(), 'last_seen_at' => now()])->save();
                $this->line("{$device->name}: {$recorded} new");
            } catch (Throwable $e) {
                $this->warn("{$device->name}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
