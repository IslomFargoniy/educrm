<?php

namespace App\Services\Isup;

use App\Models\Device;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Talks to the terminals through the ISUP gateway's ISAPI passthrough.
 */
class Gateway
{
    public function isConfigured(): bool
    {
        return filled(config('isup.token'));
    }

    /**
     * Send an ISAPI request to the device and return its raw answer (JSON or XML text).
     *
     * @throws RuntimeException when the device is offline or refuses the request
     */
    public function isapi(Device $device, string $method, string $url, string $body = '', int $timeoutSeconds = 20): string
    {
        try {
            $response = $this->http($timeoutSeconds + 5)->post('/api/isapi', [
                'device_id' => $device->isup_id,
                'method' => $method,
                'url' => $url,
                'body' => $body,
                'timeout_ms' => $timeoutSeconds * 1000,
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException(__('The device gateway is not running.'));
        }

        if ($response->status() === 404) {
            throw new RuntimeException(__('The device is offline.'));
        }

        if (! $response->successful() || ! $response->json('success')) {
            throw new RuntimeException(__('The device did not answer (code :code).', ['code' => $response->json('error_code') ?? $response->status()]));
        }

        return (string) $response->json('response');
    }

    /**
     * ISAPI JSON request that must answer statusCode 1 (or carry data).
     *
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    public function json(Device $device, string $method, string $url, ?array $payload = null, int $timeoutSeconds = 20): array
    {
        $answer = $this->isapi($device, $method, $url, $payload === null ? '' : (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $timeoutSeconds);
        $decoded = json_decode($answer, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(__('The device gave an unexpected answer.'));
        }

        if (isset($decoded['statusCode']) && (int) $decoded['statusCode'] !== 1) {
            throw new RuntimeException((string) ($decoded['subStatusCode'] ?? $decoded['statusString'] ?? 'error'));
        }

        return $decoded;
    }

    /**
     * Devices connected to the gateway right now, keyed by ISUP id.
     *
     * @return array<string, array{online: bool, ip: ?string, serial: ?string}>|null null when the gateway is not running
     */
    public function devices(): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->http(3)->get('/api/devices');
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $devices = [];

        foreach ((array) $response->json() as $device) {
            if (is_array($device) && isset($device['device_id'])) {
                $devices[(string) $device['device_id']] = [
                    'online' => (bool) ($device['online'] ?? false),
                    'ip' => isset($device['ip']) ? (string) $device['ip'] : null,
                    'serial' => isset($device['serial']) ? (string) $device['serial'] : null,
                ];
            }
        }

        return $devices;
    }

    private function http(int $timeoutSeconds): PendingRequest
    {
        return Http::baseUrl((string) config('isup.gateway_url'))
            ->withHeaders(['X-Gateway-Token' => (string) config('isup.token')])
            ->acceptJson()
            ->timeout($timeoutSeconds);
    }
}
