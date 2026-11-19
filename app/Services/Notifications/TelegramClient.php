<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;

/**
 * Minimal Telegram Bot API client.
 */
class TelegramClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.telegram.bot_token'));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function sendMessage(string|int $chatId, string $text, array $extra = []): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        return $this->call('sendMessage', ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'HTML', ...$extra]);
    }

    /**
     * Photo (a local file) with the text as its caption.
     */
    public function sendPhoto(string|int $chatId, string $path, string $caption): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $response = Http::timeout(20)
            ->attach('photo', (string) file_get_contents($path), 'photo.jpg')
            ->post($this->url('sendPhoto'), ['chat_id' => $chatId, 'caption' => $caption, 'parse_mode' => 'HTML']);

        return $response->successful() && $response->json('ok') === true;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function call(string $method, array $payload): bool
    {
        $response = Http::timeout(10)->post($this->url($method), $payload);

        return $response->successful() && $response->json('ok') === true;
    }

    private function url(string $method): string
    {
        return 'https://api.telegram.org/bot'.config('services.telegram.bot_token').'/'.$method;
    }
}
