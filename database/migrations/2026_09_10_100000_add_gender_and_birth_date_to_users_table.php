<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jenis kelamin dan tanggal lahir.
 *
 * Yang disimpan adalah TANGGAL LAHIR, bukan umur. Umur berubah sendiri setiap
 * tahun tanpa ada yang menulis apa pun ke basis data, jadi kolom `age` akan
 * salah pada hari ulang tahun setiap penggunanya dan tidak ada kejadian yang
 * bisa memicu pembaruannya. Umur dihitung di `App\Models\User::age()`.
 *
 * Kolom turunan (GENERATED) juga tidak bisa dipakai untuk ini: MySQL menolak
 * fungsi non-deterministik seperti `CURDATE()` di dalamnya — justru karena
 * alasan yang sama.
 *
 * Keduanya NULLABLE. Pendaftaran tidak berubah (lihat RegisterRequest), jadi
 * setiap baris yang sudah ada tetap sah tanpa backfill dan tanpa menebak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('gender', 6)->nullable()->after('name');
            $table->date('birth_date')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['gender', 'birth_date']);
        });
    }
};
