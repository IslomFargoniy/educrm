<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Notifications\SmsSender;
use App\Services\Notifications\TelegramClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Deliver one message to a user: Telegram when linked, otherwise SMS.
 */
class SendNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, scalar>  $params
     */
    public function __construct(public int $userId, public string $template, public array $params, public bool $telegramOnly = false, public ?string $photo = null) {}

    public function handle(TelegramClient $telegram, SmsSender $sms): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $text = __('notifications.'.$this->template, $this->params, $user->locale ?? 'uz')."\n— ".config('app.name');
        $channel = $user->telegram_chat_id && $telegram->isConfigured() ? 'telegram' : 'sms';

        if ($channel === 'sms' && ($this->telegramOnly || ! $sms->isEnabled() || ! $user->phone)) {
            return;
        }

        try {
            $photo = $this->photo !== null && Storage::disk('local')->exists($this->photo) ? Storage::disk('local')->path($this->photo) : null;

            match (true) {
                $channel === 'telegram' && $photo !== null => $telegram->sendPhoto($user->telegram_chat_id, $photo, $text),
                $channel === 'telegram' => $telegram->sendMessage($user->telegram_chat_id, $text),
                default => $sms->send($user->phone, $text),
            };
            $this->log($channel, $channel === 'telegram' ? (string) $user->telegram_chat_id : (string) $user->phone, $text, 'sent');
        } catch (Throwable $e) {
            $this->log($channel, (string) $user->phone, $text, 'failed', $e->getMessage());

            throw $e;
        }
    }

    private function log(string $channel, string $recipient, string $text, string $status, ?string $error = null): void
    {
        NotificationLog::query()->create([
            'channel' => $channel,
            'recipient' => $recipient,
            'template' => $this->template,
            'body' => $text,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
