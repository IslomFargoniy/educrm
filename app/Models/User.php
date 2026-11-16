<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'phone', 'email', 'password', 'locale', 'is_active', 'telegram_chat_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'telegram_chat_id'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'is_active' => 'boolean',
            'balance' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    /**
     * Students this user is a guardian (parent) of.
     *
     * @return BelongsToMany<Student, $this>
     */
    public function children(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'guardian_student')->withPivot('relation');
    }

    /**
     * Student profile when the user is a student.
     *
     * @return HasOne<Student, $this>
     */
    public function studentProfile(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->hasRole(Role::Superadmin->value);
    }

    /**
     * IDs of branches the user may work with.
     *
     * @return list<int>
     */
    public function accessibleBranchIds(): array
    {
        if ($this->isSuperadmin()) {
            return Branch::query()->pluck('id')->all();
        }

        return $this->branches()->pluck('branches.id')->all();
    }

    /**
     * Whether the user is staff of the given branch.
     */
    public function canAccessBranch(int $branchId): bool
    {
        return in_array($branchId, $this->accessibleBranchIds(), true);
    }
}
