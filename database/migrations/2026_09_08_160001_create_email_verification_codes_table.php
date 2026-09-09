<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode verifikasi email untuk akun yang baru mendaftar.
 *
 * Kodenya disimpan sebagai HASH, bukan teks biasa. Kode 6 angka memang hanya
 * punya sejuta kemungkinan, tapi menyimpannya apa adanya berarti siapa pun
 * yang bisa membaca tabel ini dapat mengaktifkan akun orang lain — termasuk
 * dari cadangan basis data lama.
 *
 * `attempts` membatasi percobaan pada KODE-nya, bukan pada IP, sehingga
 * rotasi IP tidak menolong penyerang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verification_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Hash bcrypt dari kode. Tidak pernah menyimpan kodenya sendiri.
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            // Untuk cooldown kirim ulang tanpa perlu cache terpisah.
            $table->timestamp('last_sent_at')->nullable();

            $table->string('request_ip', 45)->nullable();
            $table->timestamps();

            // Kode aktif milik seorang user: dipakai di hampir setiap kueri.
            $table->index(['user_id', 'consumed_at', 'expires_at']);
            // Job pembersihan kode kedaluwarsa.
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verification_codes');
    }
};
