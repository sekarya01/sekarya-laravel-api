<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `gender` dibuat VARCHAR(6) saat hanya ada `male`/`female`. Nilai ketiga
     * `prefer_not_to_say` panjangnya 17 karakter dan TIDAK muat: MySQL akan
     * memotongnya jadi `prefer` — nilai yang tidak dikenal enum, sehingga
     * setiap pembacaan baris itu melempar ValueError saat di-cast.
     *
     * 20 karakter, bukan pas 17: menyisakan ruang untuk nilai berikutnya
     * tanpa perlu ALTER TABLE lagi di tabel yang akan terus tumbuh.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Migrasi ini butuh MySQL.');
        }

        // Diubah langsung, bukan lewat Blueprint: doctrine/dbal tidak
        // terpasang, jadi `$table->string(...)->change()` tidak tersedia.
        DB::statement('ALTER TABLE `users` MODIFY `gender` VARCHAR(20) NULL');
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        // Baris yang memakai nilai baru dikosongkan dulu — dibiarkan, ia akan
        // terpotong jadi nilai yang tidak dikenal enum dan merusak pembacaan.
        DB::table('users')->where('gender', 'prefer_not_to_say')->update(['gender' => null]);

        DB::statement('ALTER TABLE `users` MODIFY `gender` VARCHAR(6) NULL');
    }
};
