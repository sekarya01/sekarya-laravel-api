<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama alamat task ("Rumah", "Kos", "Kantor") — pasangan `location_text`.
 *
 * Diisi pemberi kerja di Pasang Tugas, seperti nama di alamat tersimpan.
 * Ikut DITAHAN sampai deal bersama `location_text`: nama tempat orang adalah
 * petunjuk pribadi, bukan label feed (itu tugas `area`). NULL = tidak diisi;
 * task lama tetap sah tanpa backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('location_label', 80)->nullable()->after('location_text');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('location_label');
        });
    }
};
