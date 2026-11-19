<?php

use App\Enums\LedgerAccount;
use App\Enums\Role;
use App\Models\Branch;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\LedgerEntry;
use App\Models\LessonCharge;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DemoSimulator;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo('2026-11-19 10:00:00');
});

it('simulates months of a demo branch with consistent balances and purges it completely', function () {
    $owner = userWithRole(Role::Superadmin);
    $realBranch = Branch::factory()->create();

    $stats = app(DemoSimulator::class)->simulate(studentCount: 40, months: 2);

    expect(now()->toDateTimeString())->toBe('2026-11-19 10:00:00')
        ->and($stats['students'])->toBe(40)
        ->and($stats['lessons_held'])->toBeGreaterThan(100)
        ->and($stats['payments'])->toBeGreaterThan(40)
        ->and(Student::query()->count())->toBe(40)
        ->and(NotificationLog::query()->count())->toBe(0)
        ->and(User::query()->where('phone', 'like', DemoSimulator::PHONE_PREFIX.'%')->count())->toBeGreaterThan(40);

    // Ledger invariants for every account.
    foreach ([[LedgerAccount::Cashbox, Cashbox::query()->get()], [LedgerAccount::Teacher, User::query()->get()], [LedgerAccount::Enrollment, Enrollment::query()->get()]] as [$type, $models]) {
        foreach ($models as $model) {
            $entries = LedgerEntry::query()->where('account_type', $type)->where('account_id', $model->id)->orderBy('id');
            expect((int) $entries->sum('amount'))->toBe($model->balance)
                ->and($entries->get()->last()?->balance_after ?? 0)->toBe($model->balance);
        }
    }

    expect(Cashbox::query()->where('balance', '<', 0)->count())->toBe(0)
        ->and(LessonCharge::query()->whereColumn('covered_amount', '>', 'amount')->count())->toBe(0)
        ->and(Payment::query()->whereNotNull('reversed_at')->count())->toBeGreaterThan(0);

    $this->artisan('demo:purge', ['--force' => true])->assertSuccessful();

    expect(Branch::query()->pluck('id')->all())->toBe([$realBranch->id])
        ->and(Student::query()->count())->toBe(0)
        ->and(Transaction::query()->count())->toBe(0)
        ->and(LedgerEntry::query()->count())->toBe(0)
        ->and(User::query()->pluck('id')->all())->toBe([$owner->id]);
});

it('refuses to simulate twice', function () {
    Branch::factory()->create(['name' => DemoSimulator::BRANCH_NAME]);

    $this->artisan('demo:simulate')->assertFailed();
});
