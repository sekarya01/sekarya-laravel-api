<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cakupan wilayah kode undangan: kota + provinsi, keduanya NULL = nasional.
 *
 * Dibutuhkan endpoint ketersediaan untuk aplikasi: "apakah ada kode yang
 * hidup di kota/provinsi user?" — tanpa kolom ini jawabannya tidak bisa
 * dihitung. NULL berarti berlaku di mana saja, jadi kode lama (nasional)
 * tetap valid tanpa backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_invite_codes', function (Blueprint $table): void {
            $table->string('city', 80)->nullable()->after('code_plain');
            $table->string('province', 80)->nullable()->after('city');
            $table->index(['city', 'province']);
        });
    }

    public function down(): void
    {
        Schema::table('worker_invite_codes', function (Blueprint $table): void {
            $table->dropIndex(['city', 'province']);
            $table->dropColumn(['city', 'province']);
        });
    }
};
