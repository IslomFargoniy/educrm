<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\CurrentBranch;
use Inertia\Inertia;

abstract class Controller
{
    /**
     * Branch IDs the current page works with.
     *
     * @return list<int>
     */
    protected function branchIds(): array
    {
        return app(CurrentBranch::class)->ids();
    }

    protected function authorizeBranch(?int $branchId): void
    {
        $user = request()->user();
        abort_unless($user instanceof User && $branchId !== null && $user->canAccessBranch($branchId), 403);
    }

    /**
     * Show a toast message on the next page.
     */
    protected function toast(string $message, string $type = 'success'): void
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);
    }
}
