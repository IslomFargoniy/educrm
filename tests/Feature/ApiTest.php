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
