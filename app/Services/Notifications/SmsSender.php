<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * SMS sending through a configurable driver: "log" (default, nothing is sent) or "eskiz".
 */
class SmsSender
{
    public function isEnabled(): bool
    {
        return config('services.sms.driver', 'log') !== 'log';
    }

    public function send(string $phone, string $text): void
    {
        match (config('services.sms.driver', 'log')) {
            'eskiz' => $this->sendViaEskiz($phone, $text),
            default => Log::info('SMS', ['phone' => $phone, 'text' => $text]),
        };
    }

    private function sendViaEskiz(string $phone, string $text): void
    {
        $token = Cache::remember('eskiz_token', now()->addDays(25), function () {
            $response = Http::asForm()->post('https://notify.eskiz.uz/api/auth/login', [
                'email' => config('services.sms.eskiz_email'),
                'password' => config('services.sms.eskiz_password'),
            ]);

            return $response->json('data.token') ?? throw new RuntimeException('Eskiz login failed');
        });

        $response = Http::withToken($token)->asForm()->post('https://notify.eskiz.uz/api/message/sms/send', [
            'mobile_phone' => ltrim($phone, '+'),
            'message' => $text,
            'from' => config('services.sms.eskiz_from', '4546'),
        ]);

        if ($response->failed()) {
            Cache::forget('eskiz_token');

            throw new RuntimeException('Eskiz send failed: '.$response->body());
        }
    }
}
