<?php

use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Lead;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExpenseCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, ExpenseCategorySeeder::class, DemoSeeder::class]);
    auth()->logout();
});

it('logs in with a phone number and returns a token', function () {
    $this->postJson(route('api.login'), ['phone' => '90 123 45 67', 'password' => 'password'])
        ->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'roles', 'permissions']]);

    $this->postJson(route('api.login'), ['phone' => '+998901234567', 'password' => 'wrong'])->assertUnprocessable();
});

it('lets a teacher mark attendance of own lesson through the API', function () {
    $teacher = User::role(Role::Teacher->value)->first();
    $lesson = Lesson::factory()->create(['group_id' => Lesson::query()->where('teacher_id', $teacher->id)->first()->group_id, 'teacher_id' => $teacher->id, 'date' => today()]);
    $token = $teacher->createToken('test')->plainTextToken;

    $roster = $this->withToken($token)->getJson(route('api.lessons.show', $lesson))->assertOk()->json('students');
    $statuses = collect($roster)->mapWithKeys(fn ($s) => [$s['enrollment_id'] => 'present'])->all();

    $this->withToken($token)->postJson(route('api.lessons.attendance', $lesson), ['statuses' => $statuses])->assertOk();
    expect($lesson->attendances()->count())->toBe(count($statuses));
});

it('receives a payment through the API', function () {
    $admin = User::role(Role::Admin->value)->first();
    $enrollment = Enrollment::query()->first();
    $cashbox = Cashbox::query()->where('branch_id', $enrollment->group->branch_id)->first();
    $before = $enrollment->balance;

    $this->withToken($admin->createToken('t')->plainTextToken)
        ->postJson(route('api.payments.store', $enrollment), ['cashbox_id' => $cashbox->id, 'amount' => 50_000])
        ->assertCreated()
        ->assertJsonPath('balance', $before + 50_000);
});

it('serves public courses and turns website sign-ups into leads', function () {
    $this->getJson(route('api.public.info'))->assertOk()->assertJsonStructure(['courses' => [['name', 'price_from']], 'branches']);

    $this->postJson(route('api.public.leads'), ['full_name' => 'Test Mijoz', 'phone' => '90 555 44 33', 'course' => 'English'])->assertCreated();

    expect(Lead::query()->where('phone', '+998905554433')->first()->source->value)->toBe('website');
});

it('adds a student and a lead with first and last name separately through the API', function () {
    $admin = User::role(Role::Admin->value)->first();
    $token = $admin->createToken('test')->plainTextToken;
    $group = Group::query()->where('status', 'active')->first();

    $options = $this->withToken($token)->getJson(route('api.form-options'))->assertOk();
    expect($options->json('groups'))->not->toBeEmpty();

    $id = $this->withToken($token)->postJson(route('api.students.store'), ['first_name' => 'Ali', 'last_name' => 'Valiyev', 'phone' => '90 111 22 33', 'group_id' => $group->id])
        ->assertCreated()->assertJsonPath('student.full_name', 'Ali Valiyev')->json('student.id');
    expect(Enrollment::query()->where('student_id', $id)->where('group_id', $group->id)->exists())->toBeTrue();

    $this->withToken($token)->postJson(route('api.students.store'), ['first_name' => 'Ali'])->assertUnprocessable()->assertJsonValidationErrors('last_name');

    $this->withToken($token)->postJson(route('api.leads.store'), ['first_name' => 'Bek', 'last_name' => 'Karimov', 'phone' => '90 222 33 44', 'source' => 'instagram'])
        ->assertCreated()->assertJsonPath('lead.full_name', 'Bek Karimov');
    expect(Lead::query()->where('first_name', 'Bek')->value('last_name'))->toBe('Karimov');
});

it('saves the app language of the user through the API', function () {
    $admin = User::role(Role::Admin->value)->first();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)->putJson(route('api.me.locale'), ['locale' => 'en'])->assertOk();
    expect($admin->fresh()->locale)->toBe('en');

    $this->withToken($token)->putJson(route('api.me.locale'), ['locale' => 'de'])->assertUnprocessable();
});

it('shows the group days and time next to each lesson', function () {
    $teacher = User::role(Role::Teacher->value)->first();
    $group = Group::query()->where('teacher_id', $teacher->id)->firstOrFail();
    $group->update(['days' => [1, 3, 5], 'start_time' => '09:00', 'end_time' => '10:30']);
    Lesson::factory()->create(['group_id' => $group->id, 'teacher_id' => $teacher->id, 'date' => today()]);

    $lesson = collect($this->withToken($teacher->createToken('test')->plainTextToken)
        ->getJson(route('api.lessons', ['date' => today()->toDateString()]))->assertOk()->json('lessons'))
        ->firstWhere('group', $group->name);

    expect($lesson['group_days'])->toBe([1, 3, 5])
        ->and($lesson['group_start_time'])->toBe('09:00')
        ->and($lesson['group_end_time'])->toBe('10:30');
});

it('lists the students of the teacher\'s groups with their debts, debtors first', function () {
    $teacher = User::role(Role::Teacher->value)->first();
    $group = Group::query()->where('teacher_id', $teacher->id)->where('status', 'active')->firstOrFail();

    $response = $this->withToken($teacher->createToken('test')->plainTextToken)->getJson(route('api.me.students'))->assertOk();

    $students = collect($response->json('students'));
    expect($students)->not->toBeEmpty()
        ->and($students->pluck('group')->unique()->all())->toContain($group->name)
        ->and($students->pluck('balance')->all())->toBe($students->pluck('balance')->sort()->values()->all())
        ->and($response->json('debt'))->toBe((int) -$students->where('balance', '<', 0)->sum('balance'));
});

it('counts the students of the day\'s groups and the frozen ones', function () {
    $teacher = User::role(Role::Teacher->value)->first();
    $group = Group::query()->where('teacher_id', $teacher->id)->where('status', 'active')->firstOrFail();
    Lesson::factory()->create(['group_id' => $group->id, 'teacher_id' => $teacher->id, 'date' => today()]);
    $studying = Enrollment::query()->where('group_id', $group->id)->whereIn('status', ['trial', 'active'])->whereDate('joined_at', '<=', today())->get();
    $studying->first()->freezes()->create(['from_date' => today()->subDay(), 'to_date' => today()->addWeek(), 'reason' => 'kasal']);

    $response = $this->withToken($teacher->createToken('test')->plainTextToken)
        ->getJson(route('api.lessons', ['date' => today()->toDateString()]))->assertOk();

    $lesson = collect($response->json('lessons'))->firstWhere('group', $group->name);
    expect($lesson['students'])->toBe($studying->count())
        ->and($lesson['frozen'])->toBe(1)
        ->and($response->json('summary.frozen'))->toBeGreaterThanOrEqual(1)
        ->and($response->json('summary.students'))->toBeGreaterThanOrEqual($studying->count());
});

it('shows the teacher a group card with students, balances, phones and recent attendance', function () {
    $teacher = User::role(Role::Teacher->value)->first();
    $group = Group::query()->where('teacher_id', $teacher->id)->where('status', 'active')->firstOrFail();
    $token = $teacher->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->getJson(route('api.groups.show', $group))->assertOk()
        ->assertJsonStructure(['group' => ['name', 'days'], 'students' => [['enrollment_id', 'full_name', 'phone', 'balance', 'frozen']], 'lessons']);

    expect($response->json('group.name'))->toBe($group->name);

    $other = Group::query()->where('teacher_id', '!=', $teacher->id)->first();
    if ($other !== null) {
        $this->withToken($token)->getJson(route('api.groups.show', $other))->assertForbidden();
    }
});

it('shows a manager their own salary and KPI in the app', function () {
    $manager = User::role(Role::Manager->value)->first();
    $manager->update(['salary_fixed' => 2_000_000, 'kpi_first_payment' => 20_000]);
    $token = $manager->createToken('test')->plainTextToken;

    expect($this->withToken($token)->getJson(route('api.me'))->json('user.has_salary'))->toBeTrue();
    $this->withToken($token)->getJson(route('api.me.salary', ['month' => now()->format('Y-m')]))->assertOk()
        ->assertJson(['id' => $manager->id, 'fixed' => 2_000_000, 'kpi_rate' => 20_000])
        ->assertJsonStructure(['total', 'paid', 'left', 'first_payments', 'students']);
});

it('pages the student list for the app', function () {
    $admin = User::role(Role::Admin->value)->first();
    $token = $admin->createToken('test')->plainTextToken;

    $first = $this->withToken($token)->getJson(route('api.students'))->assertOk();
    expect($first->json('students'))->toHaveCount(min(30, $first->json('total')));

    if ($first->json('total') > 30) {
        expect($first->json('next_page'))->toBe(2);
        $second = $this->withToken($token)->getJson(route('api.students', ['page' => 2]))->assertOk();
        expect(collect($second->json('students'))->pluck('id')->intersect(collect($first->json('students'))->pluck('id')))->toBeEmpty();
    }
});

it('gives the teacher balance cards that add up, and pages the history, income and pending lists', function () {
    $teacher = User::role(Role::Teacher->value)->first();
    $token = $teacher->createToken('test')->plainTextToken;

    $summary = $this->withToken($token)->getJson(route('api.me.balance.summary'))->assertOk()->json();
    expect($summary['opening'] + $summary['income'] + $summary['earlier_debts'] - $summary['paid_out'])->toBe($summary['closing']);

    foreach (['api.me.history', 'api.me.income-students', 'api.me.pending'] as $name) {
        $this->withToken($token)->getJson(route($name, ['page' => 1]))->assertOk()->assertJsonStructure(['rows', 'next_page', 'total']);
    }
    $this->withToken($token)->getJson(route('api.me.history', ['kind' => 'payout']))->assertOk();
});
