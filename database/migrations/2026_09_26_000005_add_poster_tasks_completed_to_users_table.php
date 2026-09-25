<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penghitung "Layanan Selesai" pemberi kerja (U15).
 *
 * Di `users`, seperti `poster_rating_avg`/`poster_rating_count`: reputasi
 * sebagai PEMBERI KERJA hidup di sisi akun, terpisah dari reputasi pekerja
 * di `user_workers`. Dinaikkan sekali per task yang benar-benar `completed`,
 * bukan per pekerja yang disetujui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('poster_tasks_completed')->default(0)->after('poster_rating_count');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('poster_tasks_completed');
        });
    }
};
