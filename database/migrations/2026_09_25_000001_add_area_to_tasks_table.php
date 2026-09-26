<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wilayah kasar task (kecamatan/kelurahan) — "Dago, Bandung" di kartu feed.
 *
 * Ada karena alamat lengkap (`location_text`) dan koordinat presisi kini
 * DITAHAN sampai deal. Tanpa kolom ini kartu hanya bisa menulis kota, padahal
 * pencari kerja butuh gambaran "sejauh apa" sebelum menawar.
 *
 * Diisi pemberi kerja (atau aplikasinya) saat membuat/menyunting task — TIDAK
 * di-geocode di server: tidak ada layanan geocoding di aplikasi ini, dan
 * menambahkannya demi satu label adalah ketergantungan luar baru. NULL = tidak
 * diisi; task lama tetap sah tanpa backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('area', 80)->nullable()->after('location_text');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('area');
        });
    }
};
