<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Token perangkat untuk push notification (FCM).
 *
 * Satu pengguna boleh punya banyak baris — orang yang sama bisa memasang
 * aplikasi di ponsel dan tablet. Karena itu `user_id` BUKAN unique.
 *
 * `token` justru unique, dan itu yang menjaga keadaan yang paling sering
 * terjadi di lapangan: satu ponsel dipakai bergantian oleh beberapa akun.
 * Token FCM menempel pada PEMASANGAN aplikasi, bukan pada akun — saat orang
 * kedua login di ponsel yang sama, perangkat itu menerbitkan token yang sama.
 * Dengan `updateOrCreate` atas `token`, barisnya berpindah pemilik alih-alih
 * melahirkan dua baris yang sama-sama mengaku berhak atas notifikasi itu.
 *
 * `last_used_at` diperbarui tiap kali klien mendaftarkan ulang tokennya,
 * sehingga baris yang lama tidak dipakai lagi bisa dikenali dan disapu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // 255 karakter cukup untuk token FCM (umumnya ~160) sekaligus
            // muat sebagai indeks unique di InnoDB/utf8mb4.
            $table->string('token', 255)->unique();
            $table->string('platform', 16);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
