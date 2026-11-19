<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\PrepareDevice;
use App\Models\Device;
use App\Services\Isup\VisitRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Callbacks of the ISUP gateway: device keys, online / offline, access events.
 */
class IsupGatewayController extends Controller
{
    /**
     * The ISUP key a terminal must log in with; unknown or disabled terminals get none.
     */
    public function deviceKey(Request $request): JsonResponse
    {
        $device = Device::query()->where('isup_id', (string) $request->query('device_id'))->where('is_active', true)->first();

        return $device === null
            ? response()->json(['key' => null], 404)
            : response()->json(['key' => $device->isup_key]);
    }

    public function deviceStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'device_id' => ['required', 'string'],
            'status' => ['required', 'in:online,offline'],
            'ip' => ['nullable', 'string', 'max:64'],
            'serial' => ['nullable', 'string', 'max:64'],
        ]);
        $device = Device::query()->where('isup_id', $validated['device_id'])->first();

        if ($device === null) {
            return response()->json(['ok' => false], 404);
        }

        $online = $validated['status'] === 'online';
        $device->forceFill(array_filter([
            'is_online' => $online,
            'last_seen_at' => now(),
            'ip' => $validated['ip'] ?? null,
            'serial' => $validated['serial'] ?? null,
        ], fn ($value) => $value !== null))->save();

        if ($online) {
            PrepareDevice::dispatch($device->id);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * An access event pushed by a terminal (face recognised, card, etc.).
     */
    public function events(Request $request, VisitRecorder $visits): JsonResponse
    {
        $payload = $request->all();
        $device = Device::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->where('isup_id', (string) ($payload['gatewayDeviceId'] ?? $payload['deviceID'] ?? ''))
                ->orWhere(fn ($s) => $s->whereNotNull('serial')->where('serial', (string) ($payload['gatewaySerial'] ?? '-'))))
            ->first();

        if ($device === null) {
            return response()->json(['ok' => false], 404);
        }

        $event = is_array($payload['AccessControllerEvent'] ?? null) ? $payload['AccessControllerEvent'] : $payload;
        $visit = $visits->record($device, $event, $payload['dateTime'] ?? null, live: true);

        return response()->json(['ok' => true, 'visit_id' => $visit?->id]);
    }
}
