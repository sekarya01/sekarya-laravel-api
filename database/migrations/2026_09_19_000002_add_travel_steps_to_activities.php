<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua langkah perjalanan sebelum pekerjaan dimulai.
 *
 * `departed_at` diisi pekerja saat ia berangkat; `arrived_at` diisi pemberi
 * kerja saat ia melihat orangnya sampai. Dipisah dari `started_at` karena
 * ketiganya menjawab pertanyaan berbeda — kapan berangkat, kapan tiba, kapan
 * mulai bekerja — dan selisih di antaranya justru yang ditanyakan saat ada
 * keluhan "kok lama".
 *
 * Kolomnya nullable tanpa nilai bawaan: pekerjaan yang sudah berjalan sebelum
 * langkah ini ada memang tidak punya jawabannya, dan mengarangkan waktu
 * keberangkatan dari `opened_at` akan membuat jejak yang tidak pernah terjadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->timestamp('departed_at')->nullable()->after('opened_at');
            $table->timestamp('arrived_at')->nullable()->after('departed_at');
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->dropColumn(['departed_at', 'arrived_at']);
        });
    }
};
