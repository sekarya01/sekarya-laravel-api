<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Judul/profesi mitra ("Teknisi AC") — U16.
 *
 * Di profil pekerja, bukan `users`: ini label yang ditampilkan ke pemberi
 * kerja, dan pemberi kerja hanya melihat sisi pekerja. Nullable — baris lama
 * dan yang tidak mengisinya tetap sah; klien jatuh ke keahlian pertama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_workers', function (Blueprint $table): void {
            $table->string('headline', 60)->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('user_workers', function (Blueprint $table): void {
            $table->dropColumn('headline');
        });
    }
};
