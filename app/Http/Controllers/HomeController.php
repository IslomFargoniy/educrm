<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Send every role to its start page.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        return match (true) {
            $user->can('dashboard.view') => to_route('dashboard'),
            $user->hasRole(Role::Teacher->value) => to_route('attendance.index'),
            default => to_route('portal'),
        };
    }
}
