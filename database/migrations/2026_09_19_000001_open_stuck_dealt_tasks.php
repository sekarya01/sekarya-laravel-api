<?php

declare(strict_types=1);

use App\Support\StuckWorkBackfill;
use Illuminate\Database\Migrations\Migration;

/**
 * Migrasi DATA, bukan skema: membuka pekerjaan yang terlanjur tersangkut.
 *
 * Deal sekarang selalu membuka pekerjaannya: begitu lelang ditutup, setiap
 * orang yang diterima punya activity atas namanya. Task yang deal SEBELUM
 * aturan itu tidak ikut — tidak ada yang membuatkan barisnya surut ke belakang
 * — dan bagi pekerjanya kerjaan yang sudah jadi miliknya berhenti di "Bisa
 * dimulai setelah pembayaran dikonfirmasi", untuk konfirmasi yang tidak akan
 * pernah datang selama mekanisme pembayarannya belum ada.
 *
 * Ditaruh sebagai migrasi, bukan perintah artisan, supaya ikut jalan pada
 * `php artisan migrate` yang memang sudah ada di prosedur rilis — server
 * produksi ini tidak selalu punya SSH, dan langkah manual tambahan adalah
 * langkah yang suatu saat terlewat.
 *
 * Logikanya dipinjam `App\Support\StuckWorkBackfill` supaya bisa diuji tanpa
 * menjalankan migrasi; konsekuensinya berkas ini ikut berubah kalau kelas itu
 * berubah — dapat diterima karena ia hanya dijalankan sekali, dan pemeriksaan
 * "sudah punya activity?" membuat pengulangan tidak berakibat apa pun.
 */
return new class extends Migration
{
    public function up(): void
    {
        $opened = app(StuckWorkBackfill::class)->run();

        if ($opened !== []) {
            info('Pekerjaan yang tersangkut dibuka menyusul.', [
                'tasks' => count($opened),
            ]);
        }
    }

    /**
     * Tidak bisa dibatalkan, dan tidak seharusnya.
     *
     * Membalikkannya berarti menghapus activity yang mungkin sudah dimulai —
     * bahkan sudah diserahkan hasilnya — dan mengembalikan task ke keadaan
     * yang justru sedang diperbaiki. Menghapus pekerjaan orang untuk
     * merapikan riwayat migrasi bukan pertukaran yang sah.
     */
    public function down(): void
    {
        // sengaja kosong
    }
};
