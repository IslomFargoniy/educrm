<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CashboxMethod;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/branches', [
            'branches' => Branch::query()->whereIn('id', $request->user()->accessibleBranchIds())
                ->with(['rooms:id,branch_id,name,capacity', 'cashboxes:id,branch_id,name,method,is_active,balance'])
                ->orderBy('name')->get(),
            'methods' => array_column(CashboxMethod::cases(), 'value'),
            'canManageBranches' => $request->user()->can('branches.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('branches.manage'), 403);
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $branch = Branch::query()->create($validated);

            foreach ([CashboxMethod::Cash, CashboxMethod::Card, CashboxMethod::Click, CashboxMethod::Payme] as $method) {
                $branch->cashboxes()->create(['name' => ucfirst($method->value), 'method' => $method]);
            }
        });

        $this->toast(__('Branch created with cashboxes.'));

        return back();
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        abort_unless($request->user()->can('branches.manage'), 403);
        $branch->update($this->validated($request));
        $this->toast(__('Saved.'));

        return back();
    }

    public function storeRoom(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch->id);
        $branch->rooms()->create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]));

        return back();
    }

    public function updateRoom(Request $request, Room $room): RedirectResponse
    {
        $this->authorizeBranch($room->branch_id);
        $room->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]));

        return back();
    }

    public function destroyRoom(Room $room): RedirectResponse
    {
        $this->authorizeBranch($room->branch_id);
        $room->delete();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);
    }
}
