<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nominal transfer unik di antara isi ulang yang MENUNGGU, ditegakkan server.
 *
 * Pengelola mencocokkan mutasi bank dengan `transfer_amount`. Kode unik dipilih
 * dengan membaca kode yang sedang terpakai, tanpa kunci lintas pengguna — dua
 * permintaan bersamaan bisa memilih kode yang sama, dan bila nominalnya juga
 * sama, satu mutasi cocok ke dua permintaan. Pola `open_activity_lock`: MySQL
 * tanpa partial index, jadi kolom turunan berisi nominal hanya selama
 * menunggu, dan unique tidak menganggap dua NULL bertabrakan.
 *
 * Hanya baris berkode (`unique_code > 0`): baris sebelum kode unik ada
 * (`transfer_amount = amount`, kode 0) boleh bernominal sama — mengikutkannya
 * membuat migrasi ini gagal di data lama.
 *
 * Nominal, bukan kode: yang dicocokkan pengelola adalah nominalnya, dan saat
 * seluruh 999 kode terpakai Action jatuh ke kode acak — unique atas kode akan
 * menolak semua isi ulang berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_topups', function (Blueprint $table): void {
            $table->unsignedBigInteger('awaiting_transfer_lock')
                ->virtualAs("case when status = 'awaiting_confirmation' and unique_code > 0 then transfer_amount end")
                ->nullable();
            $table->unique('awaiting_transfer_lock');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_topups', function (Blueprint $table): void {
            $table->dropUnique(['awaiting_transfer_lock']);
            $table->dropColumn('awaiting_transfer_lock');
        });
    }
};
