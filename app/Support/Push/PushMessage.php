<?php

declare(strict_types=1);

namespace App\Support\Push;

/**
 * Satu notifikasi siap kirim, tanpa ketergantungan pada penyedia mana pun.
 *
 * Sengaja bukan tipe milik FCM: pembentuk pesan (PushMessages) dan pengirim
 * (PushNotifier) berbagi bentuk NETRAL ini, sehingga pindah penyedia push
 * tidak menyentuh tempat copy disusun.
 *
 * `data` adalah payload tambahan yang dibaca aplikasi untuk memutuskan
 * perilaku saat notifikasi diketuk (mis. membuka layar tertentu). Nilainya
 * WAJIB string — FCM menolak angka/boolean di `data`.
 */
final readonly class PushMessage
{
    /** @param array<string, string> $data */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
    ) {}
}
