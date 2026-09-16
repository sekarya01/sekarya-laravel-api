<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan yang menunggu manusia: isi saldo dan penarikan.
 *
 * Terpisah dari `wallet_entries` dan itu bukan pembagian yang bisa
 * digabungkan. Buku besar berisi HAL YANG SUDAH TERJADI — setiap barisnya
 * ikut menentukan saldo, dan saldo tidak boleh ikut bergerak karena ada orang
 * yang baru mengaku sudah transfer. Permintaan punya siklus statusnya sendiri
 * (menunggu, disetujui, ditolak, dibatalkan); baris buku besar tidak punya
 * status sama sekali.
 *
 * Hubungannya satu arah: sebuah permintaan yang disetujui MELAHIRKAN baris
 * buku besar, dan barisnya menunjuk balik lewat `reference_type`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topups', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('awaiting_confirmation');

            // Petunjuk yang membantu pengelola menemukan transfernya di
            // mutasi: nama pengirim, bank, empat digit terakhir referensi.
            // Bukan nomor rekening — tidak ada nomor rekening yang disimpan
            // di jalur ini.
            $table->string('sender_note', 200)->nullable();

            $table->string('rejection_reason', 500)->nullable();

            // Pengelola yang memutuskan. Menunjuk `admins`, bukan `users` —
            // alasan yang sama dengan `user_worker_verifications.reviewed_by`.
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('admins')->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            // Antrean pengelola: paling lama menunggu di depan.
            $table->index(['status', 'created_at', 'id']);
            // Riwayat milik satu orang.
            $table->index(['user_id', 'created_at', 'id']);
        });

        Schema::create('wallet_withdrawals', function (Blueprint $table): void {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('requested');

            // Rekening tujuan DIRUJUK, tidak disalin.
            //
            // Nomor rekening sudah tersimpan terenkripsi di
            // `user_worker_verifications.account_number_enc`, dan detail
            // verifikasi adalah satu-satunya tempat ia keluar terbaca — dengan
            // jejak baca yang dicatat. Menyalinnya ke sini berarti tempat
            // KEDUA, tanpa jejak baca, dan dua jawaban untuk pertanyaan
            // "ke rekening mana orang ini dibayar".
            //
            // restrictOnDelete: baris verifikasi tidak boleh bisa hilang
            // selama masih ada penarikan yang menunjuknya — itu akan
            // menghapus tujuan transfer dari catatan uang yang sudah keluar.
            $table->foreignId('verification_id')
                ->constrained('user_worker_verifications')->restrictOnDelete();

            $table->string('rejection_reason', 500)->nullable();

            // Referensi transfer dari sisi pengelola (nomor mutasi bank).
            $table->string('transfer_reference', 100)->nullable();

            $table->foreignId('processed_by')->nullable()
                ->constrained('admins')->nullOnDelete();

            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at', 'id']);
            $table->index(['user_id', 'created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawals');
        Schema::dropIfExists('wallet_topups');
    }
};
