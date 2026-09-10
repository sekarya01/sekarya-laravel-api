<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `user_verifications` -> `user_worker_verifications`.
 *
 * Namanya menyebut PEKERJA karena itulah yang digantungkan padanya: sejak
 * `ready_to_work` menuntut verifikasi identitas, tabel ini bukan lagi catatan
 * administratif di samping akun — ia gerbang yang menentukan siapa yang boleh
 * muncul sebagai pekerja.
 *
 * **Kolom penunjuknya tetap `user_id` ke `users`, bukan ke `user_workers`**,
 * dan itu keputusan yang diambil sadar. Pemberi kerja juga mengajukan
 * verifikasi identitas, dan badge-nya dibaca pekerja saat menimbang siapa yang
 * mempekerjakannya. Memindahkan FK ke `user_workers` akan menghapus satu-satunya
 * cara pekerja menilai lawan transaksinya. Harganya: nama tabel menyebut
 * "worker" padahal sebagian isinya milik pemberi kerja.
 *
 * INDEKS DAN FOREIGN KEY IKUT DIGANTI NAMANYA. `RENAME TABLE` di MySQL
 * membiarkan keduanya memakai nama lama, jadi tabel bernama
 * `user_worker_verifications` akan membawa `user_verifications_user_id_foreign`
 * di dalamnya — dan nama itu ikut tercetak ke `database/schema/sekarya-install.sql`,
 * berkas yang dibaca orang saat memasang aplikasi ini di hosting tanpa SSH.
 * Kerancuan yang mau dihilangkan justru pindah ke tempat yang lebih sulit
 * dilihat.
 */
return new class extends Migration
{
    private const string OLD = 'user_verifications';

    private const string NEW = 'user_worker_verifications';

    /** Indeks yang namanya ikut berpindah. Kunci: akhiran setelah nama tabel. */
    private const array INDEXES = [
        'user_id_type_status_index',
        'document_number_hash_index',
        'status_submitted_at_index',
        'created_at_index',
        'reviewed_by_reviewed_at_index',
    ];

    public function up(): void
    {
        $this->move(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->move(self::NEW, self::OLD);
    }

    /**
     * Pindahkan tabel berikut seluruh nama indeks dan foreign key-nya.
     *
     * Foreign key harus dilepas LEBIH DULU: MySQL tidak punya
     * `RENAME FOREIGN KEY`, dan sebuah indeks yang masih dipakai foreign key
     * tidak bisa diganti namanya.
     */
    private function move(string $from, string $to): void
    {
        Schema::table($from, function (Blueprint $table) use ($from): void {
            $table->dropForeign($from.'_user_id_foreign');
            $table->dropForeign($from.'_reviewed_by_foreign');
        });

        Schema::rename($from, $to);

        foreach (self::INDEXES as $suffix) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` RENAME INDEX `%s` TO `%s`',
                $to,
                $from.'_'.$suffix,
                $to.'_'.$suffix,
            ));
        }

        Schema::table($to, function (Blueprint $table): void {
            // Persis seperti aslinya: akun dihapus -> verifikasinya ikut;
            // pengelola dihapus -> jejak penilaiannya tetap, penilainya
            // dikosongkan.
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
        });
    }
};
