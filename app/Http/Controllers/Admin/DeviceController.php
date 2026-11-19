<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\PrepareDevice;
use App\Models\Branch;
use App\Models\Device;
use App\Services\Isup\Gateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Face terminals (Hikvision, ISUP 5.0) of the branches.
 */
class DeviceController extends Controller
{
    public function index(Gateway $gateway): Response
    {
        $branchIds = request()->user()->accessibleBranchIds();
        $live = $gateway->devices();

        $devices = Device::query()
            ->whereIn('branch_id', $branchIds)
            ->with('branch:id,name')
            ->withCount(['students as synced_count' => fn ($q) => $q->where('device_student.status', 'synced')])
            ->withCount(['students as failed_count' => fn ($q) => $q->where('device_student.status', 'failed')])
            ->orderBy('branch_id')->orderBy('name')
            ->get()
            ->map(fn (Device $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'branch_id' => $device->branch_id,
                'branch' => $device->branch->name,
                'direction' => $device->direction,
                'isup_id' => $device->isup_id,
                'isup_key' => $device->isup_key,
                'is_active' => $device->is_active,
                'is_online' => $live !== null ? (bool) ($live[$device->isup_id]['online'] ?? false) : $device->is_online,
                'ip' => $live[$device->isup_id]['ip'] ?? $device->ip,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'synced_count' => $device->synced_count,
                'failed_count' => $device->failed_count,
            ]);

        return Inertia::render('admin/devices', [
            'devices' => $devices,
            'branches' => Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(['id', 'name']),
            'server' => ['address' => config('isup.server_address'), 'port' => config('isup.server_port')],
            'gatewayRunning' => $live !== null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->authorizeBranch((int) $validated['branch_id']);
        Device::query()->create($validated);
        $this->toast(__('Device added. Enter its Device ID and ISUP key on the terminal.'));

        return back();
    }

    public function update(Request $request, Device $device): RedirectResponse
    {
        $this->authorizeBranch($device->branch_id);
        $validated = $this->validated($request) + $request->validate([
            'isup_id' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z0-9]{1,32}$/', Rule::unique('devices', 'isup_id')->ignore($device->id)],
            'isup_key' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z0-9]{8,32}$/'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
        $this->authorizeBranch((int) $validated['branch_id']);
        $device->update($validated);
        $this->toast(__('Saved.'));

        return back();
    }

    public function destroy(Device $device): RedirectResponse
    {
        $this->authorizeBranch($device->branch_id);
        $device->delete();
        $this->toast(__('Device deleted.'));

        return back();
    }

    /**
     * Write every studying student of the branch (with faces) to the terminal again.
     */
    public function sync(Device $device): RedirectResponse
    {
        $this->authorizeBranch($device->branch_id);
        PrepareDevice::dispatch($device->id, all: true);
        $this->toast(__('Students are being sent to the device. It takes a few minutes.'));

        return back();
    }

    /**
     * @return array{branch_id: int, name: string, direction: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'name' => ['required', 'string', 'max:100'],
            'direction' => ['required', 'in:in,out'],
        ]);
    }
}
