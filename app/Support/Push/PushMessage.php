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
 *
 * `silent` = pesan data-only: tidak menggambar notifikasi, hanya membangunkan
 * aplikasi untuk menyinkron (dipakai chat: tanda baca, pesan dihapus, room
 * berubah). Pesan senyap tidak pernah masuk kotak masuk lonceng.
 *
 * `drawnByApp` = tetap pesan yang BERNOTIFIKASI, tapi dikirim data-only agar
 * aplikasi yang menggambarnya sendiri (chat: gaya pesan per room + tombol
 * Balas/Tandai dibaca) — juga saat aplikasi di belakang/di-kill. Datanya
 * wajib membawa `title`/`body` untuk digambar.
 */
final readonly class PushMessage
{
    /** @param array<string, string> $data */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
        public bool $silent = false,
        public bool $drawnByApp = false,
    ) {}

    /**
     * Salinan dengan `data` lain — dipakai job push untuk menyisipkan
     * snapshot per penerima (hanya ke FCM, bukan ke baris lonceng).
     *
     * @param  array<string, string>  $data
     */
    public function withData(array $data): self
    {
        return new self($this->title, $this->body, $data, $this->silent, $this->drawnByApp);
    }
}
