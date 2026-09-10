<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak tindakan pengelola. Append-only: tidak ada `updated_at`, karena jejak
 * yang bisa disunting bukan jejak (pola yang sama dengan `task_status_logs`).
 *
 * Satu tabel untuk semua tindakan, bukan kolom `disetujui_oleh` di setiap
 * tabel yang dinilai. Dua alasan:
 *
 *  - Penolakan tidak menyisakan baris. Verifikasi yang ditolak lalu diajukan
 *    ulang menimpa barisnya sendiri, dan pembayaran yang ditolak kembali ke
 *    `pending`. Tanpa tabel ini, keputusan-keputusan itu hilang seluruhnya —
 *    justru keputusan yang paling perlu bisa ditinjau ulang.
 *  - Tindakan berikutnya (dan akan ada) tidak menuntut migrasi baru.
 *
 * `subject_type` + `subject_id` adalah pasangan polimorfis manual berisi slug
 * pendek (`user_verification`, `payment`, `user`, `admin`) — bukan nama kelas
 * PHP. Nama kelas ikut berubah saat kode dirapikan, dan jejak yang menunjuk
 * kelas yang sudah tidak ada tidak bisa dibaca lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();

            // restrictOnDelete: baris jejak tidak boleh bisa dihapus dengan
            // cara menghapus pelakunya. Admin memakai soft delete, jadi
            // penghapusan normal tidak pernah menyentuh batasan ini.
            $table->foreignId('admin_id')->constrained()->restrictOnDelete();

            $table->string('action', 48);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');

            $table->string('reason', 500)->nullable();
            $table->string('ip', 45)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['admin_id', 'created_at']);
            $table->index(['subject_type', 'subject_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
