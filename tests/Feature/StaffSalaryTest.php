<?php

use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Group;
use App\Models\Student;
use App\Models\Transaction;
use App\Services\EnrollmentService;
use App\Services\FinanceService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

it('pays an administrator the fixed salary plus 20 000 for each new student whose first payment fell in the month', function () {
    $this->travelTo('2026-10-20 10:00:00');
    $owner = userWithRole(Role::Superadmin);
    $group = Group::factory()->priced(600_000, 12, '2026-10-01')->create();
    $admin = userWithRole(Role::Admin, [$group->branch]);
    $admin->update(['salary_fixed' => 2_500_000, 'kpi_first_payment' => 20_000]);
    $cashbox = Cashbox::factory()->create(['branch_id' => $group->branch_id, 'balance' => 0]);
    $finance = app(FinanceService::class);
    $enroll = fn (Student $s) => app(EnrollmentService::class)->enroll($s, $group, CarbonImmutable::parse('2026-10-01'));

    $this->actingAs($admin);
    $paidInOctober = Student::factory()->create(['branch_id' => $group->branch_id]);
    $first = Student::factory()->create(['branch_id' => $group->branch_id]);
    $second = Student::factory()->create(['branch_id' => $group->branch_id]);
    $notPaid = Student::factory()->create(['branch_id' => $group->branch_id]);
    $finance->receivePayment($enroll($paidInOctober), $cashbox, 100_000);
    $firstEnrollment = $enroll($first);
    $secondEnrollment = $enroll($second);
    $enroll($notPaid);

    $this->travelTo('2026-11-05 10:00:00');
    $finance->receivePayment($firstEnrollment->fresh(), $cashbox->fresh(), 100_000);
    $finance->receivePayment($firstEnrollment->fresh(), $cashbox->fresh(), 50_000);
    $finance->receivePayment($secondEnrollment->fresh(), $cashbox->fresh(), 100_000);
    $finance->receivePayment($enroll(tap(Student::factory()->create(['branch_id' => $group->branch_id]), fn ($s) => $s->update(['registered_by' => $owner->id]))), $cashbox->fresh(), 100_000);

    expect($first->fresh()->registered_by)->toBe($admin->id);

    $this->actingAs($owner);
    $this->get(route('staff-salaries.index', ['month' => '2026-11']))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('staff-salaries')
        ->has('staff', 1)
        ->where('staff.0.first_payments', 2)
        ->where('staff.0.kpi', 40_000)
        ->where('staff.0.total', 2_540_000)
        ->where('staff.0.paid', 0));

    $this->post(route('staff-salaries.pay', $admin), ['cashbox_id' => $cashbox->id, 'amount' => 300_000, 'month' => '2026-11'])->assertSessionHasNoErrors();

    expect(Transaction::query()->where('meta->staff_id', $admin->id)->sum('amount'))->toBe(300_000);
    $this->get(route('staff-salaries.index', ['month' => '2026-11']))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('staff.0.paid', 300_000)->where('staff.0.left', 2_240_000));
});
