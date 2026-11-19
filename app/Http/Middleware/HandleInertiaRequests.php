<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\User;
use App\Support\CurrentBranch;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        /** @var User|null $user */
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'auth' => [
                'user' => $user?->only(['id', 'name', 'first_name', 'last_name', 'phone', 'email', 'locale', 'two_factor_enabled', 'created_at', 'updated_at']),
                'roles' => fn () => $user?->getRoleNames()->all() ?? [],
                'permissions' => fn () => $user === null ? [] : ($user->isSuperadmin()
                    ? Permissions::all()
                    : $user->getAllPermissions()->pluck('name')->all()),
            ],
            'branches' => fn () => $user === null ? [] : Branch::query()
                ->whereIn('id', $user->accessibleBranchIds())
                ->orderBy('name')
                ->get(['id', 'name']),
            'currentBranchId' => fn () => $user === null ? null : app(CurrentBranch::class)->id(),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
