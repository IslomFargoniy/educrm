<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Cashbox;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;

beforeEach(function () {
    $this->travelTo('2026-11-10 12:00:00');
    $this->group = Group::factory()->priced(600_000, 12, '2026-11-01')->create();
    $this->manager = userWithRole(Role::Manager, [$this->group->branch]);
});

it('finds students to take a payment from on the cashbox page and the payment goes through', function () {
    $student = Student::factory()->create(['branch_id' => $this->group->branch_id, 'full_name' => 'Sitora Bekova', 'phone' => '+998901112233']);
    $enrollment = Enrollment::factory()->create(['student_id' => $student->id, 'group_id' => $this->group->id, 'balance' => -150_000]);
    $cashbox = Cashbox::factory()->create(['branch_id' => $this->group->branch_id]);
    $this->actingAs($this->manager);

    $this->getJson(route('payments.students', ['q' => 'sitora']))
        ->assertOk()
        ->assertJsonPath('data.0.id', $student->id)
        ->assertJsonPath('data.0.enrollments.0.id', $enrollment->id)
        ->assertJsonPath('data.0.enrollments.0.balance', -150_000);
    $this->getJson(route('payments.students', ['q' => '111 22']))->assertJsonPath('data.0.id', $student->id);

    $this->post(route('enrollments.payments.store', $enrollment), ['cashbox_id' => $cashbox->id, 'amount' => 150_000])
        ->assertSessionHasNoErrors();
    expect($enrollment->fresh()->balance)->toBe(0);
});

it('shows only students of the user branches and needs the payment permission', function () {
    $otherGroup = Group::factory()->create(['branch_id' => Branch::factory()->create()->id]);
    $stranger = Student::factory()->create(['branch_id' => $otherGroup->branch_id, 'full_name' => 'Sitora Begona']);
    Enrollment::factory()->create(['student_id' => $stranger->id, 'group_id' => $otherGroup->id]);
    $this->actingAs($this->manager);

    $this->getJson(route('payments.students', ['q' => 'sitora']))->assertOk()->assertJsonCount(0, 'data');
    $this->getJson(route('payments.students', ['q' => 's']))->assertJsonCount(0, 'data');

    $this->actingAs(userWithRole(Role::Teacher, [$this->group->branch]));
    $this->getJson(route('payments.students', ['q' => 'sitora']))->assertForbidden();
});
