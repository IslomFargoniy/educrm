<?php

use App\Enums\Role;
use App\Models\Cashbox;
use App\Models\Enrollment;
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

    expect(\App\Models\Lead::query()->where('phone', '+998905554433')->first()->source->value)->toBe('website');
});
