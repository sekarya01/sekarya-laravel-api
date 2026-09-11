<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Skema-prod dan test adalah MySQL (lihat phpunit.xml). SQLite
        // tidak didukung migrasi ini — gagal di awal sebelum ada yang berubah.
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            throw new RuntimeException('Migrasi identitas butuh MySQL.');
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('first_name', 60)->nullable()->after('name');
            $table->string('last_name', 60)->nullable()->after('first_name');
            $table->string('username', 30)->nullable()->unique()->after('last_name');
        });

        // `phone` jadi nullable tanpa doctrine/dbal (paketnya tidak
        // terpasang): ubah langsung. Unique tetap — MySQL membolehkan
        // banyak NULL di kolom unique.
        DB::statement('ALTER TABLE `users` MODIFY `phone` VARCHAR(20) NULL');

        // Backfill portabel: "Budi Prasetyo" -> first Budi + last Prasetyo,
        // satu kata -> first saja, last NULL.
        DB::table('users')->whereNull('first_name')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $parts = preg_split('/\s+/', trim((string) $row->name), 2);
                DB::table('users')->where('id', $row->id)->update([
                    'first_name' => $parts[0] !== '' ? $parts[0] : $row->name,
                    'last_name' => $parts[1] ?? null,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'last_name', 'first_name']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `phone` VARCHAR(20) NOT NULL');
        }
    }
};
