<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role as RoleName;
use App\Http\Controllers\Controller;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/roles', [
            'roles' => Role::query()->where('name', '!=', RoleName::Superadmin->value)->with('permissions:id,name')->get()
                ->map(fn (Role $role) => ['id' => $role->id, 'name' => $role->name, 'permissions' => $role->permissions->pluck('name')]),
            'groups' => Permissions::grouped(),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        abort_if($role->name === RoleName::Superadmin->value, 403);
        $validated = $request->validate([
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permissions::all())],
        ]);
        $role->syncPermissions($validated['permissions'] ?? []);
        $this->toast(__('Permissions saved.'));

        return back();
    }
}
