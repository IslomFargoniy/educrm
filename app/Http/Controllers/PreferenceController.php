<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreferenceController extends Controller
{
    /**
     * Change the interface language.
     */
    public function locale(Request $request): RedirectResponse
    {
        $validated = $request->validate(['locale' => ['required', Rule::in(['uz', 'ru', 'en'])]]);

        $request->user()?->update(['locale' => $validated['locale']]);

        return back()->withCookie(cookie()->forever('locale', $validated['locale']));
    }

    /**
     * Switch the branch the staff member works in ("all" for superadmin).
     */
    public function branch(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validate(['branch_id' => ['required']]);
        $branchId = $validated['branch_id'];

        if ($branchId === 'all') {
            abort_unless($user->isSuperadmin(), 403);
        } else {
            $this->authorizeBranch((int) $branchId);
        }

        $request->session()->put('branch_id', $branchId);

        return back();
    }
}
