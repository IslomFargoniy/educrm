<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Permissions that can be switched on per user (what a teacher sees about students).
     */
    private const PERSONAL_PERMISSIONS = ['students.view_phone', 'students.view_debt', 'students.view_payments'];

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $branchIds = $actor->accessibleBranchIds();

        $users = User::query()->role(array_map(fn (Role $r) => $r->value, Role::staff()))
            ->when(! $actor->isSuperadmin(), fn ($q) => $q->whereHas('branches', fn ($b) => $b->whereIn('branches.id', $branchIds)))
            ->with('roles:id,name', 'branches:id,name', 'permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                ...$user->only(['id', 'name', 'phone', 'email', 'is_active', 'balance']),
                'role' => $user->getRoleNames()->first(),
                'branch_ids' => $user->branches->pluck('id'),
                'branches' => $user->branches->pluck('name'),
                'personal_permissions' => $user->permissions->pluck('name')->intersect(self::PERSONAL_PERMISSIONS)->values(),
            ]);

        return Inertia::render('admin/users', [
            'users' => $users,
            'branches' => Branch::query()->whereIn('id', $branchIds)->orderBy('name')->get(['id', 'name']),
            'roles' => $this->assignableRoles($actor),
            'personalPermissions' => self::PERSONAL_PERMISSIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $password = $validated['password'] ?? Str::password(8, symbols: false);

        DB::transaction(function () use ($validated, $password) {
            $user = User::query()->create([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'password' => $password,
            ]);
            $user->syncRoles([$validated['role']]);
            $user->branches()->sync($validated['branch_ids'] ?? []);
            $user->syncPermissions($validated['personal_permissions'] ?? []);
        });

        $this->toast(__('User created. Login: :phone, password: :password', ['phone' => $validated['phone'], 'password' => $password]));

        return back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUser($request, $user);
        $validated = $this->validated($request, $user);

        if ($user->isSuperadmin() && $validated['role'] !== Role::Superadmin->value && User::role(Role::Superadmin->value)->count() === 1) {
            throw ValidationException::withMessages(['role' => __('The last superadmin cannot be changed.')]);
        }

        DB::transaction(function () use ($user, $validated) {
            $user->update([
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'email' => $validated['email'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                ...(! empty($validated['password']) ? ['password' => $validated['password']] : []),
            ]);
            $user->syncRoles([$validated['role']]);
            $user->branches()->sync($validated['branch_ids'] ?? []);
            $user->syncPermissions($validated['personal_permissions'] ?? []);
        });

        $this->toast(__('Saved.'));

        return back();
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUser($request, $user);
        $password = Str::password(8, symbols: false);
        $user->update(['password' => $password]);
        $user->tokens()->delete();
        $this->toast(__('New password for :name: :password', ['name' => $user->name, 'password' => $password]));

        return back();
    }

    /**
     * @return list<string>
     */
    private function assignableRoles(User $actor): array
    {
        $roles = [Role::Admin->value, Role::Manager->value, Role::Teacher->value];

        return $actor->isSuperadmin() ? [Role::Superadmin->value, ...$roles] : $roles;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'regex:/^\+998\d{9}$/', Rule::unique('users', 'phone')->ignore($user?->id)],
            'email' => ['nullable', 'email', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in($this->assignableRoles($request->user()))],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'is_active' => ['boolean'],
            'personal_permissions' => ['array'],
            'personal_permissions.*' => [Rule::in(self::PERSONAL_PERMISSIONS)],
        ]);

        foreach ($validated['branch_ids'] ?? [] as $branchId) {
            $this->authorizeBranch((int) $branchId);
        }

        return $validated;
    }

    private function authorizeUser(Request $request, User $user): void
    {
        $actor = $request->user();

        if ($actor->isSuperadmin()) {
            return;
        }

        abort_if($user->isSuperadmin(), 403);
        abort_unless($user->branches()->whereIn('branches.id', $actor->accessibleBranchIds())->exists(), 403);
    }
}
