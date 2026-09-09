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

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],

    // JANGAN '*' di produksi. Daftar asal diambil dari env supaya tiap
    // lingkungan menyatakan sendiri siapa yang boleh memanggil.
    // Contoh: CORS_ALLOWED_ORIGINS="https://app.sekarya.id,https://admin.sekarya.id"
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:5173')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],

    // Klien perlu membaca header ini untuk menampilkan sisa kuota
    // dan tahu kapan boleh mencoba lagi.
    'exposed_headers' => ['X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After'],

    'max_age' => 86400,

    // API ini memakai Bearer token, bukan cookie sesi. Membiarkannya false
    // menutup seluruh kelas serangan CSRF lintas asal.
    'supports_credentials' => false,

];
