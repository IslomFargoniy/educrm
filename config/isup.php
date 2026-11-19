<?php

return [
    /*
    | Local REST API of the ISUP gateway (scripts/isup-gateway) and the token both sides share.
    */
    'gateway_url' => env('ISUP_GATEWAY_URL', 'http://127.0.0.1:7671'),
    'token' => env('ISUP_GATEWAY_TOKEN'),

    /*
    | What the admin types into the terminal (Platform Access → ISUP).
    */
    'server_address' => env('ISUP_SERVER_ADDRESS', '93.92.112.90'),
    'server_port' => (int) env('ISUP_SERVER_PORT', 7660),

    /*
    | New devices get "<prefix>-<number>" as Device ID and this key (random when empty).
    */
    'device_id_prefix' => env('ISUP_DEVICE_ID_PREFIX', parse_url((string) env('APP_URL', 'http://educrm'), PHP_URL_HOST) ?: 'educrm'),
    'default_key' => env('ISUP_DEFAULT_KEY'),

    /*
    | Base URL the terminals download face photos from (plain http works on every firmware).
    */
    'face_base_url' => env('ISUP_FACE_BASE_URL', env('APP_URL')),

    /*
    | A pass this long before a lesson starts (or during it) marks the student as arrived for it.
    */
    'arrival_window_minutes' => 60,

    'time_zone' => 'CST-5:00:00',
];
