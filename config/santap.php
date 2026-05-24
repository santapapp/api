<?php

declare(strict_types=1);

return [
    'api' => [
        'version' => env('SANTAP_API_VERSION', 'v1'),
    ],

    'qris' => [
        'base_url' => env('QRIS_BASE_URL', 'https://qris.sekeco.id'),
    ],
];
