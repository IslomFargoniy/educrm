<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Phone + password login for the Android app; returns a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::query()->where('phone', Phone::normalize($validated['phone']))->first();

        if ($user === null || ! $user->is_active || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        return response()->json([
            'token' => $user->createToken($validated['device_name'] ?? 'android')->plainTextToken,
            'user' => $this->profile($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->profile($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profile(User $user): array
    {
        return [
            ...$user->only(['id', 'name', 'phone', 'locale', 'balance']),
            'roles' => $user->getRoleNames(),
            'has_salary' => $user->salary_fixed !== null || $user->kpi_first_payment !== null,
            'permissions' => $user->isSuperadmin() ? Permissions::all() : $user->getAllPermissions()->pluck('name'),
            'branches' => $user->branches()->get(['branches.id', 'branches.name']),
        ];
    }
}
