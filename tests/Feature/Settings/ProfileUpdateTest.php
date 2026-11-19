<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
});

test('profile information and language can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com', 'locale' => 'ru'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->locale)->toBe('ru');
});

test('email is optional', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['first_name' => 'Test', 'last_name' => 'User', 'email' => null, 'locale' => 'uz'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->email)->toBeNull();
});
