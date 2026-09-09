<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Kategori pekerjaan + harga referensi AWAL.
 *
 * ref_sample_size = 0 pada semua baris di bawah, dan itu penting:
 * angka harga ini masih perkiraan manual, belum dihitung dari task yang selesai.
 * UI wajib membedakannya (lihat `from_real_data` di CategoryResource) — menampilkan
 * nilai seed seolah data nyata akan menyesatkan pemberi kerja sejak hari pertama.
 *
 * RecomputeReferencePricesAction akan menggantinya dengan angka nyata
 * begitu ada task selesai per kategori.
 */
final class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['mencuci', 'Mencuci', 'Cuci pakaian, setrika, cuci kering', 'washing-machine', 50_000, 150_000, 80_000],
            ['bersih-rumah', 'Membersihkan Rumah', 'Bersih-bersih rumah, kamar, dapur, kamar mandi', 'broom', 75_000, 300_000, 150_000],
            ['jaga-hewan', 'Menjaga Hewan', 'Titip hewan, jalan-jalan, beri makan', 'paw', 50_000, 200_000, 100_000],
            ['antar-barang', 'Mengantarkan Barang', 'Antar dokumen, paket, barang dalam kota', 'package', 15_000, 100_000, 35_000],
            ['tukang', 'Tukang & Perbaikan', 'Perbaikan kecil, pasang, servis rumah', 'wrench', 100_000, 1_000_000, 250_000],
            ['jaga-anak', 'Menjaga Anak', 'Menemani dan menjaga anak', 'baby', 75_000, 300_000, 150_000],
            ['berkebun', 'Berkebun', 'Rawat tanaman, potong rumput, bersihkan halaman', 'sprout', 75_000, 250_000, 125_000],
            ['pindahan', 'Bantu Pindahan', 'Angkat, kemas, bantu pindah barang', 'truck', 150_000, 1_000_000, 350_000],
            ['lainnya', 'Lainnya', 'Pekerjaan yang belum masuk kategori di atas', 'ellipsis', null, null, null],
        ];

        foreach ($categories as $i => [$slug, $name, $description, $icon, $min, $max, $median]) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'description' => $description,
                    'icon' => $icon,
                    'ref_price_min' => $min,
                    'ref_price_max' => $max,
                    'ref_price_median' => $median,
                    // 0 = masih seed manual, BUKAN data nyata.
                    'ref_sample_size' => 0,
                    'ref_computed_at' => null,
                    'is_active' => true,
                    'sort_order' => $i,
                ],
            );
        }
    }
}
