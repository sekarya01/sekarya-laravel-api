<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (push notification)
    |--------------------------------------------------------------------------
    |
    | Pengiriman push memakai FCM HTTP v1, yang menuntut access token OAuth2
    | bertanda tangan kunci privat service account. Kredensialnya TIDAK ikut
    | di git: simpan berkas JSON service account di luar repositori (atau di
    | `storage/app/firebase/` yang diabaikan git) lalu tunjuk lewat
    | FIREBASE_CREDENTIALS.
    |
    | `enabled = false` membuat seluruh pengiriman dilewati dengan tenang —
    | pengembangan lokal dan suite test tidak butuh kredensial produksi.
    | Mengaktifkannya tanpa berkas yang sah juga aman: FcmClient menganggap
    | dirinya tidak siap dan tidak mengirim apa pun.
    |
    */

    'enabled' => filter_var(env('FIREBASE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),

    // Path absolut ke berkas JSON service account. Kosong = tidak aktif.
    'credentials' => env('FIREBASE_CREDENTIALS'),

    // Batas waktu satu panggilan HTTP ke Google (detik).
    'timeout' => (float) env('FIREBASE_TIMEOUT', 10.0),

];
