<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ketersediaan mitra ("Siap menerima kerja") — U13.
 *
 * Sebelumnya hanya disimpan di perangkat, jadi pemberi kerja tidak pernah
 * tahu siapa yang sedang libur. Bawaan TRUE tanpa backfill: setiap pekerja
 * yang sudah ada tetap tampil seperti sebelum kolom ini lahir, dan yang
 * menonaktifkan harus memilihnya sendiri.
 *
 * Indeks `(is_available, created_at, id)` melayani `GET workers?available=1`:
 * penyaring kesetaraan di depan, lalu kolom urutan cursor yang sama dengan
 * `UserWorker::scopeLatestFirst()` — tanpa filesort.
 *
 * `down()` tanpa `after()` — lihat catatan di CLAUDE.md soal kolom yang
 * sudah tidak ada saat rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_workers', function (Blueprint $table): void {
            $table->boolean('is_available')->default(true)->after('radius_km');
            $table->index(['is_available', 'created_at', 'id'], 'user_workers_available_latest_index');
        });
    }

    public function down(): void
    {
        Schema::table('user_workers', function (Blueprint $table): void {
            $table->dropIndex('user_workers_available_latest_index');
            $table->dropColumn('is_available');
        });
    }
};
