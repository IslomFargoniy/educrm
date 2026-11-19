<?php

use App\Enums\Role;
use App\Models\Group;
use App\Models\Lead;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->actingAs(userWithRole(Role::Superadmin));
    $this->group = Group::factory()->create(['days' => [1, 3, 5]]);
    $this->lead = fn (string $name, string $status = 'new') => Lead::query()->create(['branch_id' => $this->group->branch_id, 'full_name' => $name, 'phone' => '+998901112233', 'source' => 'instagram', 'status' => $status]);
});

it('keeps requests and first-lesson sign-ups in separate lists', function () {
    $request = ($this->lead)('Buyurtma');
    $signed = ($this->lead)('Yozilgan');

    $this->put(route('leads.trial', $signed), ['trial_group_id' => $this->group->id, 'trial_at' => '2026-11-25 14:00'])->assertSessionHasNoErrors();

    expect($signed->fresh())->status->value->toBe('trial')->trial_group_id->toBe($this->group->id);

    $this->get(route('leads.index', ['list' => 'orders']))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('leads.data', 1)->where('leads.data.0.full_name', 'Buyurtma'));
    $this->get(route('leads.index', ['list' => 'trial']))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('leads.data', 1)->where('leads.data.0.full_name', 'Yozilgan')->where('leads.data.0.trial_group', $this->group->name));
    $this->get(route('leads.index', ['list' => 'trial', 'parity' => 'even']))->assertInertia(fn (AssertableInertia $page) => $page->has('leads.data', 0));

    $this->put(route('leads.trial', $signed), ['trial_group_id' => null, 'trial_at' => null])->assertSessionHasNoErrors();
    expect($signed->fresh())->trial_at->toBeNull()->status->value->toBe('contacted');
});

it('only signs up for a group of the lead\'s branch', function () {
    $other = Group::factory()->create();

    $this->put(route('leads.trial', ($this->lead)('Ali')), ['trial_group_id' => $other->id, 'trial_at' => '2026-11-25 14:00'])
        ->assertSessionHasErrors('trial_group_id');
});
