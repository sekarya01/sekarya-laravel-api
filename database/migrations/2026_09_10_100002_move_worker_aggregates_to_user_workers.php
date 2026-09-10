<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reputasi pekerja PINDAH dari `users` ke `user_workers`.
 *
 * Dipindah, bukan disalin. Dua kolom dengan nama sama di dua tabel adalah dua
 * jawaban untuk satu pertanyaan, dan yang kedua tidak pernah ketahuan salah:
 * `AcceptBidAction` menaikkan salah satunya, Resource membaca yang lain, dan
 * angkanya cuma diam-diam berhenti bergerak.
 *
 * Datanya dibawa lebih dulu, kolomnya dihapus belakangan — dalam satu migrasi,
 * supaya tidak ada keadaan antara yang bisa ditinggalkan kalau prosesnya putus.
 *
 * `poster_rating_*` dan `tasks_posted` TIDAK ikut: itu reputasi sebagai
 * pemberi kerja, dan pemberi kerja tidak punya profil terpisah.
 */
return new class extends Migration
{
    /** Kolom yang berpindah, berikut definisinya untuk jalur balik. */
    private const array MOVED = [
        'worker_rating_avg',
        'worker_rating_count',
        'tasks_completed',
        'bids_won',
    ];

    public function up(): void
    {
        // Hanya baris yang benar-benar punya riwayat. Membuat profil pekerja
        // untuk setiap akun berarti puluhan ribu baris nol yang tidak menjawab
        // pertanyaan apa pun — profilnya lahir saat orangnya mulai bekerja.
        DB::statement(<<<'SQL'
            INSERT INTO user_workers (
                user_id, worker_rating_avg, worker_rating_count,
                tasks_completed, bids_won, created_at, updated_at
            )
            SELECT
                id, worker_rating_avg, worker_rating_count,
                tasks_completed, bids_won, NOW(), NOW()
            FROM users
            WHERE worker_rating_avg  > 0
               OR worker_rating_count > 0
               OR tasks_completed     > 0
               OR bids_won            > 0
        SQL);

        Schema::table('users', function (Blueprint $table): void {
            // Indeksnya dulu: MySQL menolak menghapus kolom yang masih dipakai
            // sebuah indeks.
            $table->dropIndex(['worker_rating_avg', 'tasks_completed']);
            $table->dropColumn(self::MOVED);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // TANPA `after()`. Godaannya besar — kolom-kolom ini dulu duduk
            // persis sesudah `users.skills` — tapi kolom itu sudah dihapus
            // ketika keahlian menjadi relasi, jadi `after('skills')` membuat
            // seluruh rollback gagal dengan "Unknown column 'skills'". Urutan
            // kolom tidak menentukan apa pun; jalur balik yang bisa dijalankan,
            // iya.
            $table->decimal('worker_rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('worker_rating_count')->default(0);
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('bids_won')->default(0);

            $table->index(['worker_rating_avg', 'tasks_completed']);
        });

        DB::statement(<<<'SQL'
            UPDATE users
            JOIN user_workers ON user_workers.user_id = users.id
            SET users.worker_rating_avg   = user_workers.worker_rating_avg,
                users.worker_rating_count = user_workers.worker_rating_count,
                users.tasks_completed     = user_workers.tasks_completed,
                users.bids_won            = user_workers.bids_won
        SQL);
    }
};
