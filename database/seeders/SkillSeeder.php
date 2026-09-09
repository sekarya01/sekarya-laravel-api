<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Seeder;

/**
 * Keahlian, dikelompokkan per kategori.
 *
 * Lebih rinci daripada kategori: kategori "Membersihkan Rumah" bisa berisi
 * keahlian "cuci AC", "bersih kamar mandi", "poles lantai". Pencari kerja
 * memfilter feed dengan ini; pemberi kerja menandai task dengan ini.
 */
final class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $byCategory = [
            'mencuci' => ['setrika', 'cuci-tangan', 'cuci-mesin', 'lipat-pakaian', 'cuci-sepatu'],
            'bersih-rumah' => ['bersih-umum', 'cuci-ac', 'bersih-kamar-mandi', 'poles-lantai', 'bersih-dapur', 'cuci-jendela'],
            'jaga-hewan' => ['jaga-kucing', 'jaga-anjing', 'grooming', 'jalan-anjing', 'beri-makan-hewan'],
            'antar-barang' => ['antar-dokumen', 'antar-paket', 'antar-makanan', 'kurir-motor', 'kurir-mobil'],
            'tukang' => ['listrik', 'pipa-air', 'kayu', 'cat-dinding', 'pasang-keramik', 'servis-atap'],
            'jaga-anak' => ['jaga-bayi', 'jaga-anak-balita', 'antar-jemput-sekolah', 'temani-belajar'],
            'berkebun' => ['potong-rumput', 'rawat-tanaman', 'tebang-dahan', 'bersih-halaman'],
            'pindahan' => ['angkat-barang', 'kemas-barang', 'bongkar-pasang-mebel'],
            'lainnya' => ['antre', 'belanja-titipan', 'input-data', 'fotografi'],
        ];

        $order = 0;

        foreach ($byCategory as $categorySlug => $slugs) {
            $category = Category::query()->where('slug', $categorySlug)->first();

            foreach ($slugs as $slug) {
                Skill::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => str_replace('-', ' ', ucfirst($slug)),
                        'category_id' => $category?->getKey(),
                        'is_active' => true,
                        'sort_order' => $order++,
                    ],
                );
            }
        }
    }
}
