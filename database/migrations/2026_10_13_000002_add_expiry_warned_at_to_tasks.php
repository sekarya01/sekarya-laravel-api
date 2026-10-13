<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kapan pemberi kerja diperingatkan bahwa task-nya akan kedaluwarsa padahal
 * sudah ada pekerja yang diterima (keputusan pemilik 2026-10-13).
 *
 * Kolom, bukan cache: peringatan dikirim SEKALI per tenggat, dan command
 * penutup lelang berjalan tiap 5 menit — tanpa penanda tersimpan, pemberi kerja
 * menerima belasan notifikasi yang sama. NULL lagi saat tenggatnya diubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->timestamp('expiry_warned_at')->nullable()->after('bidding_closes_at');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('expiry_warned_at');
        });
    }
};
