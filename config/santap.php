<?php

declare(strict_types=1);

return [
    'api' => [
        'version' => env('SANTAP_API_VERSION', 'v1'),
    ],

    'organization' => [
        'default_country' => env('SANTAP_DEFAULT_COUNTRY', 'ID'),
        'default_currency' => env('SANTAP_DEFAULT_CURRENCY', 'IDR'),
        'default_timezone' => env('SANTAP_DEFAULT_TIMEZONE', 'Asia/Jakarta'),
    ],

    'customer_session' => [
        'ttl_minutes' => (int) env('SANTAP_CUSTOMER_SESSION_TTL_MINUTES', 720),
    ],
];
