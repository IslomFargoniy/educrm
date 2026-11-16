<?php

namespace App\Console\Commands;

use App\Services\Notifications\TelegramClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('telegram:set-webhook')]
#[Description('Register the Telegram bot webhook URL')]
class SetTelegramWebhook extends Command
{
    public function handle(TelegramClient $telegram): int
    {
        if (! $telegram->isConfigured() || blank(config('services.telegram.webhook_secret'))) {
            $this->error('Set TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET first.');

            return self::FAILURE;
        }

        $url = route('telegram.webhook', ['secret' => config('services.telegram.webhook_secret')]);
        $ok = $telegram->call('setWebhook', ['url' => $url, 'allowed_updates' => ['message']]);
        $this->line($ok ? "Webhook set: {$url}" : 'Failed to set webhook.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
