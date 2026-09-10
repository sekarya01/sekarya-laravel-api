<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transfer dilaporkan pemberi kerja, ditahan oleh pengelola.
 *
 * Sebelum ini, endpoint pemberi kerja langsung memindahkan pembayaran ke
 * `held` — status yang membuka activity dan mengikat seluruh tagihan. Artinya
 * pemberi kerja menyatakan sendiri bahwa uangnya sudah masuk. Sebagai stub
 * gateway itu memadai; sebagai alur transfer manual, itu berarti pekerjaan
 * bisa dimulai tanpa uang yang benar-benar diterima.
 *
 * Sekarang laporan dan konfirmasi terpisah, dan HANYA sisi pengelola yang
 * bisa mencapai `held` — satu jalur, bukan dua.
 *
 * Dua kolom di sini adalah kolom yang dibaca PEMBERI KERJA, bukan jejak
 * audit (jejaknya di `admin_audit_logs`):
 *
 *  - `reported_at` — antrean pengelola diurutkan dari yang paling lama
 *    menunggu. `updated_at` tidak bisa dipakai untuk itu: ia bergerak setiap
 *    kali baris tersentuh alasan apa pun.
 *  - `rejection_reason` — pemberi kerja harus tahu mengapa laporannya
 *    ditolak, tanpa perlu diberi akses ke tabel jejak pengelola.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('reported_at')->nullable()->after('amount');
            $table->string('rejection_reason')->nullable()->after('reported_at');

            // Antrean pengelola: satu status, diurutkan menurut lama menunggu.
            $table->index(['status', 'reported_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['status', 'reported_at']);
            $table->dropColumn(['reported_at', 'rejection_reason']);
        });
    }
};
