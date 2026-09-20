<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan kode undangan apa adanya di `code_plain`.
 *
 * Menyimpang dari kebiasaan (kode verifikasi email hanya menyimpan hash),
 * dan itu keputusan produk yang disengaja: pengelola butuh MELIHAT kode ini
 * terus-menerus untuk dibagikan ke calon mitra — kode yang hanya tampil
 * sekali tidak memenuhi kebutuhan itu.
 *
 * Konsekuensinya diterima dengan dua pengimbang:
 *  1. Kolom ini hanya keluar lewat endpoint/menu KHUSUS pengelola
 *     (guard admin + jejak audit), tidak pernah ke aplikasi pengguna.
 *  2. Setiap penerbitan dan penonaktifan tercatat di `admin_audit_logs`,
 *     jadi kode bocor bisa dilacak sumbernya lalu dimatikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_invite_codes', function (Blueprint $table): void {
            $table->string('code_plain', 8)->nullable()->after('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('worker_invite_codes', function (Blueprint $table): void {
            $table->dropColumn('code_plain');
        });
    }
};
