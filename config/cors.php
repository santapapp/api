<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    /*
    | Paths yang di-cover CORS.
    | Route API menggunakan prefix /v1/* (bukan /api/*), jadi keduanya dicantumkan.
    | 'broadcasting/auth' wajib di-cover: customer web (origin santap.app) meng-authorize
    | private channel open-bill.{billId} lewat endpoint ini secara cross-origin
    | (mengirim header X-Public-Token). Tanpa ini, preflight gagal dan notifikasi
    | real-time (OpenBillRepeatOrderCreated) tidak pernah sampai ke browser.
    */
    'paths' => [
        'v1/*',
        'api/*',
        'broadcasting/auth',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS',
    ],

    /*
    | Daftar origin yang diizinkan secara eksplisit.
    | JANGAN gunakan wildcard '*' di production — header Authorization
    | dan X-Public-Token tidak dikirim jika credentials diaktifkan.
    */
    'allowed_origins' => [
        // --- Local development ---
        'http://localhost:3000',
        'http://127.0.0.1:3000',

        // --- Production ---
        'https://santap.app',
        'https://www.santap.app',
    ],

    'allowed_origins_patterns' => [],

    /*
    | Header yang diizinkan dari request frontend.
    | X-Public-Token digunakan oleh customer API (scan QR / sesi customer).
    */
    'allowed_headers' => [
        'Content-Type',
        'Accept',
        'Authorization',
        'X-Requested-With',
        'X-Org-ID',
        'X-Public-Token',
        'X-Customer-Session',
    ],

    /*
    | Header yang boleh dibaca oleh frontend dari response.
    */
    'exposed_headers' => [],

    /*
    | Cache durasi preflight OPTIONS request (dalam detik).
    | 86400 = 24 jam.
    */
    'max_age' => 86400,

    /*
    | Harus false jika allowed_origins menggunakan wildcard.
    | Set true jika frontend mengirim cookie / session (Sanctum SPA).
    | Untuk customer API yang stateless, cukup false.
    */
    'supports_credentials' => false,

];
