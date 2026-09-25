<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tag pujian pada penilaian ("Tepat Waktu", "Kerja Rapi") — U6.
 *
 * JSON, bukan pivot: tag hanya DITAMPILKAN di kartu ulasan dan tidak pernah
 * dipakai menyaring. Aturan "data yang disaring wajib kolom/pivot berindeks"
 * tidak berlaku; kalau suatu hari ada filter per tag, ia harus pindah ke
 * pivot lebih dulu.
 *
 * NULLABLE tanpa backfill: penilaian lama memang tidak punya tag, dan
 * ReviewResource mengeluarkannya sebagai `[]` — bentuk yang sama dengan
 * penilaian baru tanpa tag.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->json('tags')->nullable()->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn('tags');
        });
    }
};
