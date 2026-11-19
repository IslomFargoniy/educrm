<?php

use App\Enums\Role;
use App\Models\Branch;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['services.telegram.webhook_secret' => 'secret', 'services.telegram.bot_token' => 'token']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
});

function shareContact(string $phone, int $chatId = 555): array
{
    return ['message' => ['chat' => ['id' => $chatId], 'contact' => ['phone_number' => $phone, 'user_id' => $chatId]]];
}

it('links a student who shares the number on the student card', function () {
    $branch = Branch::factory()->create();
    $student = Student::factory()->create(['branch_id' => $branch->id, 'first_name' => 'Abdulla', 'last_name' => 'Bekov', 'phone' => '+998330123322']);
    $sibling = Student::factory()->create(['branch_id' => $branch->id, 'phone' => '+998330123322']);

    $this->postJson(route('telegram.webhook', 'secret'), shareContact('998330123322'))->assertOk();

    $user = User::query()->where('phone', '+998330123322')->firstOrFail();
    expect($user->telegram_chat_id)->toBe('555')
        ->and($user->hasRole(Role::Student->value))->toBeTrue()
        ->and($student->fresh()->user_id)->toBe($user->id)
        ->and($sibling->fresh()->user_id)->toBe($user->id);
    Http::assertSent(fn ($request) => str_contains((string) $request['text'], 'Abdulla Bekov'));
});

it('still refuses a number nobody at the center has', function () {
    $this->postJson(route('telegram.webhook', 'secret'), shareContact('998901112233'))->assertOk();

    expect(User::query()->where('phone', '+998901112233')->exists())->toBeFalse();
});
