<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CashboxMethod;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Cashbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashboxSettingsController extends Controller
{
    public function store(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeBranch($branch->id);
        $branch->cashboxes()->create($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'method' => ['required', Rule::enum(CashboxMethod::class)],
        ]));

        return back();
    }

    public function update(Request $request, Cashbox $cashbox): RedirectResponse
    {
        $this->authorizeBranch($cashbox->branch_id);
        $cashbox->update($request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]));

        return back();
    }
}
