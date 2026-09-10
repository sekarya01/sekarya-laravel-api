<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `user_verifications.reviewed_by` menunjuk `admins`, dan sekarang dijamin
 * foreign key.
 *
 * Kolomnya sudah ada sejak awal sebagai `unsignedBigInteger` tanpa batasan —
 * dibuat sebelum ada tabel yang bisa dirujuknya. Bigint tanpa foreign key
 * menerima angka apa pun, jadi "siapa yang menyetujui" bisa menunjuk baris
 * yang tidak pernah ada tanpa satu pun galat.
 *
 * Nilai lama dikosongkan lebih dulu. Sebelum migrasi ini tidak ada satu jalur
 * pun yang mengisi kolom ini (SubmitVerificationAction justru menulisnya
 * null), tapi kalau ada basis data yang sempat mengisinya, angkanya adalah id
 * `users` — angka yang di tabel `admins` menunjuk orang lain sama sekali.
 * Lebih baik hilang daripada salah tunjuk.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('user_verifications')
            ->whereNotNull('reviewed_by')
            ->update(['reviewed_by' => null]);

        Schema::table('user_verifications', function (Blueprint $table): void {
            $table->foreign('reviewed_by')->references('id')->on('admins')->nullOnDelete();
            $table->index(['reviewed_by', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('user_verifications', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['reviewed_by', 'reviewed_at']);
        });
    }
};
