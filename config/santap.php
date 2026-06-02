<?php

declare(strict_types=1);

return [
    'api' => [
        'version' => env('SANTAP_API_VERSION', 'v1'),
    ],

    'qris' => [
        'base_url' => env('QRIS_BASE_URL', 'https://qris.sekeco.id'),

        // Window pembayaran QRIS (menit). Disetel mendekati expiry_time Midtrans
        // (~15 menit) agar tidak ada false-expire saat pembayaran masih diproses.
        'expiry_minutes' => (int) env('QRIS_EXPIRY_MINUTES', 15),
    ],
];
