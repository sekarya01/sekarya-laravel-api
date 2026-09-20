<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kode undangan pendaftaran mitra pekerja.
 *
 * Kode 8 karakter (huruf + angka + special char) TIDAK PERNAH disimpan
 * plain — yang disimpan sha256-nya di `code_hash`. Kode pendek tidak bisa
 * di-bcrypt seperti kode verifikasi email: bcrypt tidak bisa dicari balik
 * (`where code_hash = ?`), sedangkan di sini server harus menemukan barisnya
 * dari kode yang diketik user. sha256 deterministik menjawab itu, dan dengan
 * 8 char dari alfabet ~70+ simbol (≈ 10^14 kombinasi) brute force offline
 * tetap tidak praktis tanpa akses baca tabel.
 *
 * Umur kode ditentukan dua pintu, salah satu menutup sudah cukup:
 *  1. `used_count >= max_uses` — kuota habis.
 *  2. `expires_at` lewat — tanggal kedaluwarsa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worker_invite_codes', function (Blueprint $table): void {
            $table->id();
            // sha256 hex (64 char). Unique: dua kode plain berbeda tidak boleh
            // menempati hash yang sama, dan lookup redeem memakai kolom ini.
            $table->string('code_hash', 64)->unique();
            // Prefix 2 char plain untuk dikenali admin tanpa membuka kodenya
            // (mis. "A7-…"). Bukan rahasia, hanya label.
            $table->string('prefix', 8)->nullable();
            $table->unsignedInteger('max_uses')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 255)->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });

        Schema::create('worker_invite_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invite_code_id')->constrained('worker_invite_codes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['invite_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_invite_redemptions');
        Schema::dropIfExists('worker_invite_codes');
    }
};
