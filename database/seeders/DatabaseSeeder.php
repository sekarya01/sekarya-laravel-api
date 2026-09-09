<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Data acuan yang dibutuhkan aplikasi untuk berjalan.
 *
 * Catatan: WithoutModelEvents sengaja TIDAK dipakai. Trait itu mematikan
 * hook `creating` yang mengisi `ulid` — kolom NOT NULL unique — sehingga
 * seeding gagal dengan cara yang membingungkan.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CategorySeeder::class,
            // Keahlian merujuk kategori, jadi harus setelahnya.
            SkillSeeder::class,
        ]);
    }
}
