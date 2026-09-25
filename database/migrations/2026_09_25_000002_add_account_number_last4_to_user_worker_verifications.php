<?php

declare(strict_types=1);

use App\Support\BankAccountLast4Backfill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Empat digit terakhir nomor rekening, untuk "BCA · •••• 4910" di layar tarik
 * saldo.
 *
 * Kolom sendiri, bukan didekripsi saat dibaca: daftar verifikasi dan daftar
 * penarikan tidak boleh punya jalur kode yang memegang nomor UTUH — sama
 * seperti antrean verifikasi pengelola yang secara harfiah tidak punya kode
 * untuk mengeluarkan NIK. Empat digit terakhir tidak cukup untuk apa pun
 * selain mengenali rekening sendiri; bank mencetaknya di struk.
 *
 * Baris lama disusulkan sekali jalan oleh `BankAccountLast4Backfill` (logika
 * di kelas itu supaya bisa diuji tanpa menjalankan migrasi). Yang gagal
 * didekripsi (APP_KEY berbeda) dibiarkan NULL — masker hilang, tidak ada yang
 * rusak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_worker_verifications', function (Blueprint $table): void {
            $table->char('account_number_last4', 4)->nullable()->after('account_number_enc');
        });

        app(BankAccountLast4Backfill::class)->run();
    }

    public function down(): void
    {
        Schema::table('user_worker_verifications', function (Blueprint $table): void {
            $table->dropColumn('account_number_last4');
        });
    }
};
