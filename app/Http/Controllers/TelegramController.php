<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use App\Models\User;
use App\Services\Notifications\TelegramClient;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TelegramController extends Controller
{
    public function __construct(private TelegramClient $telegram) {}

    /**
     * Bot webhook: link the account by shared contact, answer balance and attendance questions.
     */
    public function webhook(Request $request, string $secret): JsonResponse
    {
        abort_unless(hash_equals((string) config('services.telegram.webhook_secret'), $secret), 404);

        $message = $request->input('message');
        $chatId = data_get($message, 'chat.id');

        if ($chatId === null) {
            return response()->json(['ok' => true]);
        }

        $user = User::query()->where('telegram_chat_id', $chatId)->first();
        $locale = $user?->locale ?? 'uz';
        app()->setLocale($locale);

        if ($contact = data_get($message, 'contact')) {
            $this->link($chatId, $contact);

            return response()->json(['ok' => true]);
        }

        $text = trim((string) data_get($message, 'text'));

        if ($user === null || $text === '/start') {
            $this->telegram->sendMessage($chatId, __('Welcome to :app! Please share your phone number to link your account.', ['app' => config('app.name')]), [
                'reply_markup' => ['keyboard' => [[['text' => __('Share phone number'), 'request_contact' => true]]], 'resize_keyboard' => true, 'one_time_keyboard' => true],
            ]);

            return response()->json(['ok' => true]);
        }

        match ($text) {
            __('Balance') => $this->sendBalance($user),
            __('Attendance') => $this->sendAttendance($user),
            default => $this->sendMenu($user),
        };

        return response()->json(['ok' => true]);
    }

    /**
     * Telegram Mini App entry page.
     */
    public function miniApp(): View
    {
        return view('tma');
    }

    /**
     * Log the Mini App user in by verified Telegram init data.
     */
    public function miniAppLogin(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->verifiedInitData((string) $request->input('init_data'));
        $telegramId = data_get($data, 'user.id');
        $user = $telegramId ? User::query()->where('telegram_chat_id', $telegramId)->where('is_active', true)->first() : null;

        if ($user === null) {
            return response()->json(['message' => __('Open the bot and share your phone number first.')], 403);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json(['redirect' => route('home')]);
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function link(int|string $chatId, array $contact): void
    {
        $user = User::query()->where('phone', Phone::normalize((string) ($contact['phone_number'] ?? '')))->first();

        if ($user === null || (int) ($contact['user_id'] ?? 0) !== (int) $chatId) {
            $this->telegram->sendMessage($chatId, __('This phone number is not registered at the center. Please contact the administrator.'));

            return;
        }

        User::query()->where('telegram_chat_id', $chatId)->whereKeyNot($user->id)->update(['telegram_chat_id' => null]);
        $user->update(['telegram_chat_id' => $chatId]);
        app()->setLocale($user->locale ?? 'uz');
        $this->telegram->sendMessage($chatId, __('Account linked: :name. You will receive notifications here.', ['name' => $user->name]));
        $this->sendMenu($user);
    }

    private function sendMenu(User $user): void
    {
        $keyboard = [[['text' => __('Balance')], ['text' => __('Attendance')]]];

        if (str_starts_with((string) config('app.url'), 'https://')) {
            $keyboard[] = [['text' => config('app.name'), 'web_app' => ['url' => route('tma')]]];
        }

        $this->telegram->sendMessage($user->telegram_chat_id, __('Choose an action:'), [
            'reply_markup' => ['keyboard' => $keyboard, 'resize_keyboard' => true],
        ]);
    }

    private function sendBalance(User $user): void
    {
        $lines = $this->studentsOf($user)->flatMap(fn (Student $student) => $student->enrollments()->with('group:id,name')
            ->whereIn('status', ['active', 'trial'])->get()
            ->map(fn ($e) => "<b>{$student->full_name}</b> — {$e->group->name}: ".number_format($e->balance, 0, '.', ' ').' '.__('sum')));

        if ($user->balance !== 0 || $user->can('attendance.mark')) {
            $lines->push(__('Your balance').': '.number_format($user->balance, 0, '.', ' ').' '.__('sum'));
        }

        $this->telegram->sendMessage($user->telegram_chat_id, $lines->isEmpty() ? __('No data yet.') : $lines->implode("\n"));
    }

    private function sendAttendance(User $user): void
    {
        $lines = $this->studentsOf($user)->flatMap(function (Student $student) {
            $records = Attendance::query()
                ->whereIn('enrollment_id', $student->enrollments()->pluck('id'))
                ->with('lesson:id,date,group_id', 'lesson.group:id,name')
                ->latest('id')->limit(10)->get();

            return collect(["<b>{$student->full_name}</b>"])->merge($records->map(fn (Attendance $a) => $a->lesson->date->format('d.m').' '.$a->lesson->group->name.': '.__('attendance.'.$a->status->value)));
        });

        $this->telegram->sendMessage($user->telegram_chat_id, $lines->isEmpty() ? __('No data yet.') : $lines->implode("\n"));
    }

    /**
     * @return Collection<int, Student>
     */
    private function studentsOf(User $user)
    {
        return $user->children()->get()->merge($user->studentProfile ? [$user->studentProfile] : [])->unique('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function verifiedInitData(string $initData): array
    {
        parse_str($initData, $data);
        $hash = $data['hash'] ?? '';
        unset($data['hash']);
        ksort($data);
        $check = collect($data)->map(fn ($value, $key) => "{$key}={$value}")->implode("\n");
        $secret = hash_hmac('sha256', (string) config('services.telegram.bot_token'), 'WebAppData', true);

        abort_unless(filled(config('services.telegram.bot_token')) && hash_equals(hash_hmac('sha256', $check, $secret), (string) $hash), 403);
        abort_if(isset($data['auth_date']) && (int) $data['auth_date'] < now()->subDay()->timestamp, 403);

        $data['user'] = json_decode((string) ($data['user'] ?? '{}'), true);

        return $data;
    }
}
