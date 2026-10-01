<?php

$csv = static fn (string $v): array => array_values(array_filter(array_map('trim', explode(',', $v))));

return [
    'timezone' => env('DTR_TIMEZONE', 'Asia/Manila'),

    'engine' => [
        'url'             => rtrim((string) env('DTR_ENGINE_URL', 'http://127.0.0.1:5001'), '/'),
        'timeout'         => (int) env('DTR_ENGINE_TIMEOUT', 20),
        'connect_timeout' => (int) env('DTR_ENGINE_CONNECT_TIMEOUT', 3),
    ],

    'kiosk_ips' => $csv((string) env('DTR_KIOSK_IPS', '')),

    'intranet_cidrs' => $csv((string) env(
        'DTR_INTRANET_CIDRS',
        '127.0.0.1/32,::1/128,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'
    )),

    'punch' => [
        'ticket_ttl_seconds'   => (int) env('DTR_TICKET_TTL', 60),
        'max_shift_hours'      => (int) env('DTR_MAX_SHIFT_HOURS', 20),
        'min_interval_seconds' => (int) env('DTR_MIN_PUNCH_INTERVAL', 60),
    ],

    'frames' => [
        'recognize_min' => 3,
        'recognize_max' => 5,
        'register_min'  => 5,
        'register_max'  => 10,
    ],

    'images' => [
        'disk'      => env('DTR_IMAGE_DISK', 'local'), 
        'directory' => 'dtr',
        'max_bytes' => 2_000_000,
    ],
];
