<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ubah default users.status dari 'active' menjadi 'pending_verification'.
 *
 * Ini perbaikan keamanan, bukan kerapian.
 *
 * Sebelumnya `status` tidak termasuk atribut yang boleh di-mass-assign,
 * sehingga `User::create(['status' => PendingVerification, ...])` membuang
 * nilai itu tanpa suara dan default kolom yang berlaku. Akibatnya akun baru
 * tercipta berstatus 'active' — melewati verifikasi email sama sekali.
 *
 * Yang menyelamatkan alur waktu itu hanya kebetulan: LoginAction juga
 * memeriksa `email_verified_at`. Kalau ada satu saja jalur yang menilai
 * kelayakan dari `status` saja, akun tak terverifikasi akan diperlakukan
 * sebagai aktif.
 *
 * Pelajarannya: default sebuah kolom yang memberi hak akses harus selalu
 * keadaan PALING TIDAK berhak. Dengan default ini, bug serupa gagal ke arah
 * yang aman — akun terkunci, bukan terbuka.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 24)->default('pending_verification')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('status', 24)->default('active')->change();
        });
    }
};
