<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The branch the staff member is currently working in (stored in the session).
 * Superadmin may pick "all branches" (null).
 */
final class CurrentBranch
{
    public function __construct(private Request $request) {}

    public function id(): ?int
    {
        $user = $this->request->user();

        if (! $user instanceof User) {
            return null;
        }

        $accessible = $user->accessibleBranchIds();
        $selected = $this->request->hasSession() ? $this->request->session()->get('branch_id') : null;

        if ($selected === 'all' && $user->isSuperadmin()) {
            return null;
        }

        if ($selected !== null && in_array((int) $selected, $accessible, true)) {
            return (int) $selected;
        }

        if ($user->isSuperadmin() && $selected === null) {
            return null;
        }

        return $accessible[0] ?? null;
    }

    /**
     * Branch IDs in scope for lists and reports.
     *
     * @return list<int>
     */
    public function ids(): array
    {
        $id = $this->id();

        if ($id !== null) {
            return [$id];
        }

        $user = $this->request->user();

        return $user instanceof User ? $user->accessibleBranchIds() : [];
    }

    public function model(): ?Branch
    {
        $id = $this->id();

        return $id === null ? null : Branch::query()->find($id);
    }

    /**
     * Branch for creating records: the current one, or the first accessible.
     */
    public function forCreate(): ?int
    {
        return $this->id() ?? ($this->ids()[0] ?? null);
    }
}
