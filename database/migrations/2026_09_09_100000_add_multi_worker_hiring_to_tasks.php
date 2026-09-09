<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu task bisa merekrut BANYAK pekerja.
 *
 * Sebelumnya "satu task = satu pekerja" ditegakkan di tiga tempat sekaligus:
 * `tasks.accepted_bid_id` unique, `activities.task_id` unique, dan
 * `activities.payment_id` unique. Ketiganya harus dilonggarkan bersama — kalau
 * hanya satu yang dibuka, penerimaan pekerja kedua akan gagal di lapisan
 * berikutnya dengan galat constraint yang tidak bisa dibaca siapa pun.
 *
 * `workers_needed` punya DUA arti sekaligus, dan itu disengaja:
 *
 *   1. Berapa orang yang akan direkrut (jumlah slot).
 *   2. Berapa lamaran yang boleh masuk (kuota pelamar).
 *
 * Jadi task 30 orang menerima paling banyak 30 lamaran, dan pemberi kerja
 * menyeleksi dari 30 itu. Lamaran yang DITOLAK tidak mengembalikan kuota —
 * kalau 5 ditolak, yang bekerja 25 orang dan sisa slotnya kosong. Lamaran yang
 * DITARIK sendiri oleh pelamar mengembalikan kuota, karena orang itu tidak
 * pernah jadi kandidat.
 *
 * Kolom pekerja-tunggal (`worker_id`, `accepted_bid_id`) DIHAPUS, tidak
 * disimpan "untuk yang satu orang". Menyimpannya berarti dua sumber kebenaran
 * untuk pertanyaan yang sama — siapa yang mengerjakan task ini — dan yang satu
 * pasti akan melenceng dari yang lain. Sumbernya sekarang satu: baris `bids`
 * berstatus accepted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            // Default 1 supaya seluruh task yang sudah ada tetap bermakna sama
            // persis seperti sebelumnya.
            $table->unsignedInteger('workers_needed')->default(1)->after('status');

            // Penghitung ter-denormalisasi. Sumber kebenarannya tetap tabel
            // `bids`; kolom ini ada supaya feed dan pemeriksaan kuota tidak
            // perlu COUNT di setiap permintaan.
            $table->unsignedInteger('workers_hired')->default(0)->after('workers_needed');

            $table->index(['status', 'workers_needed']);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropForeign(['worker_id']);
            $table->dropIndex(['worker_id', 'status', 'created_at']);
            $table->dropColumn('worker_id');

            $table->dropUnique(['accepted_bid_id']);
            $table->dropColumn('accepted_bid_id');
        });

        // Indeks baru DULU, baru unique lama dibuang: MySQL menolak menghapus
        // indeks terakhir yang menopang sebuah foreign key.
        Schema::table('activities', function (Blueprint $table): void {
            // Pengganti `task_id` unique: satu orang paling banyak satu activity
            // per task. Ini yang mencegah pekerja yang sama dibuka dua kali.
            $table->unique(['task_id', 'worker_id']);
            $table->index('payment_id');
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropUnique(['task_id']);
            $table->dropUnique(['payment_id']);
        });

        // Pemberi kerja menilai SETIAP pekerja, jadi kuncinya harus menyertakan
        // siapa yang dinilai. Tanpa ini ia hanya bisa menilai satu dari 30.
        Schema::table('reviews', function (Blueprint $table): void {
            $table->unique(['task_id', 'reviewer_id', 'reviewee_id']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['task_id', 'reviewer_id']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->unique(['task_id', 'reviewer_id']);
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropUnique(['task_id', 'reviewer_id', 'reviewee_id']);
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->unique('task_id');
            $table->unique('payment_id');
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropUnique(['task_id', 'worker_id']);
            $table->dropIndex(['payment_id']);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('worker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('accepted_bid_id')->nullable()->unique();
            $table->index(['worker_id', 'status', 'created_at']);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['status', 'workers_needed']);
            $table->dropColumn(['workers_needed', 'workers_hired']);
        });
    }
};
