<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

/**
 * Master awal kabupaten/kota (B12) — ibukota provinsi + kota besar tiap
 * provinsi. Bukan daftar BPS lengkap 514 baris; ia sengaja dipangkas ke kota
 * yang benar-benar muncul sebagai lokasi pekerjaan, dan bisa ditambah tanpa
 * mengubah kode (endpoint membaca tabelnya).
 */
final class CitySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Aceh
            ['Banda Aceh', 'Aceh', 'kota'], ['Lhokseumawe', 'Aceh', 'kota'],
            ['Langsa', 'Aceh', 'kota'], ['Sabang', 'Aceh', 'kota'], ['Meulaboh', 'Aceh', 'kabupaten'],
            // Sumatera Utara
            ['Medan', 'Sumatera Utara', 'kota'], ['Binjai', 'Sumatera Utara', 'kota'],
            ['Pematangsiantar', 'Sumatera Utara', 'kota'], ['Tebing Tinggi', 'Sumatera Utara', 'kota'],
            ['Sibolga', 'Sumatera Utara', 'kota'], ['Padangsidimpuan', 'Sumatera Utara', 'kota'],
            ['Deli Serdang', 'Sumatera Utara', 'kabupaten'],
            // Sumatera Barat
            ['Padang', 'Sumatera Barat', 'kota'], ['Bukittinggi', 'Sumatera Barat', 'kota'],
            ['Payakumbuh', 'Sumatera Barat', 'kota'], ['Solok', 'Sumatera Barat', 'kota'],
            ['Pariaman', 'Sumatera Barat', 'kota'], ['Padang Panjang', 'Sumatera Barat', 'kota'],
            // Riau
            ['Pekanbaru', 'Riau', 'kota'], ['Dumai', 'Riau', 'kota'], ['Kampar', 'Riau', 'kabupaten'],
            // Kepulauan Riau
            ['Batam', 'Kepulauan Riau', 'kota'], ['Tanjungpinang', 'Kepulauan Riau', 'kota'],
            ['Bintan', 'Kepulauan Riau', 'kabupaten'],
            // Jambi
            ['Jambi', 'Jambi', 'kota'], ['Sungai Penuh', 'Jambi', 'kota'],
            // Bengkulu
            ['Bengkulu', 'Bengkulu', 'kota'], ['Curup', 'Bengkulu', 'kabupaten'],
            // Sumatera Selatan
            ['Palembang', 'Sumatera Selatan', 'kota'], ['Prabumulih', 'Sumatera Selatan', 'kota'],
            ['Pagar Alam', 'Sumatera Selatan', 'kota'], ['Lubuklinggau', 'Sumatera Selatan', 'kota'],
            // Kepulauan Bangka Belitung
            ['Pangkalpinang', 'Kepulauan Bangka Belitung', 'kota'],
            ['Sungailiat', 'Kepulauan Bangka Belitung', 'kabupaten'],
            // Lampung
            ['Bandar Lampung', 'Lampung', 'kota'], ['Metro', 'Lampung', 'kota'],
            ['Lampung Selatan', 'Lampung', 'kabupaten'],
            // Banten
            ['Serang', 'Banten', 'kota'], ['Tangerang', 'Banten', 'kota'],
            ['Tangerang Selatan', 'Banten', 'kota'], ['Cilegon', 'Banten', 'kota'],
            ['Pandeglang', 'Banten', 'kabupaten'],
            // DKI Jakarta
            ['Jakarta Pusat', 'DKI Jakarta', 'kota'], ['Jakarta Selatan', 'DKI Jakarta', 'kota'],
            ['Jakarta Timur', 'DKI Jakarta', 'kota'], ['Jakarta Barat', 'DKI Jakarta', 'kota'],
            ['Jakarta Utara', 'DKI Jakarta', 'kota'],
            // Jawa Barat
            ['Bandung', 'Jawa Barat', 'kota'], ['Bekasi', 'Jawa Barat', 'kota'],
            ['Bogor', 'Jawa Barat', 'kota'], ['Depok', 'Jawa Barat', 'kota'],
            ['Cimahi', 'Jawa Barat', 'kota'], ['Sukabumi', 'Jawa Barat', 'kota'],
            ['Cirebon', 'Jawa Barat', 'kota'], ['Tasikmalaya', 'Jawa Barat', 'kota'],
            ['Banjar', 'Jawa Barat', 'kota'], ['Garut', 'Jawa Barat', 'kabupaten'],
            ['Karawang', 'Jawa Barat', 'kabupaten'],
            // Jawa Tengah
            ['Semarang', 'Jawa Tengah', 'kota'], ['Surakarta', 'Jawa Tengah', 'kota'],
            ['Magelang', 'Jawa Tengah', 'kota'], ['Pekalongan', 'Jawa Tengah', 'kota'],
            ['Salatiga', 'Jawa Tengah', 'kota'], ['Tegal', 'Jawa Tengah', 'kota'],
            ['Banyumas', 'Jawa Tengah', 'kabupaten'], ['Kudus', 'Jawa Tengah', 'kabupaten'],
            ['Jepara', 'Jawa Tengah', 'kabupaten'],
            // DI Yogyakarta
            ['Yogyakarta', 'DI Yogyakarta', 'kota'], ['Sleman', 'DI Yogyakarta', 'kabupaten'],
            ['Bantul', 'DI Yogyakarta', 'kabupaten'], ['Kulon Progo', 'DI Yogyakarta', 'kabupaten'],
            ['Gunungkidul', 'DI Yogyakarta', 'kabupaten'],
            // Jawa Timur
            ['Surabaya', 'Jawa Timur', 'kota'], ['Malang', 'Jawa Timur', 'kota'],
            ['Kediri', 'Jawa Timur', 'kota'], ['Blitar', 'Jawa Timur', 'kota'],
            ['Madiun', 'Jawa Timur', 'kota'], ['Mojokerto', 'Jawa Timur', 'kota'],
            ['Pasuruan', 'Jawa Timur', 'kota'], ['Probolinggo', 'Jawa Timur', 'kota'],
            ['Batu', 'Jawa Timur', 'kota'], ['Sidoarjo', 'Jawa Timur', 'kabupaten'],
            ['Gresik', 'Jawa Timur', 'kabupaten'], ['Jember', 'Jawa Timur', 'kabupaten'],
            ['Banyuwangi', 'Jawa Timur', 'kabupaten'],
            // Bali
            ['Denpasar', 'Bali', 'kota'], ['Badung', 'Bali', 'kabupaten'],
            ['Gianyar', 'Bali', 'kabupaten'], ['Tabanan', 'Bali', 'kabupaten'],
            ['Buleleng', 'Bali', 'kabupaten'],
            // Nusa Tenggara Barat
            ['Mataram', 'Nusa Tenggara Barat', 'kota'], ['Bima', 'Nusa Tenggara Barat', 'kota'],
            ['Lombok Timur', 'Nusa Tenggara Barat', 'kabupaten'], ['Sumbawa', 'Nusa Tenggara Barat', 'kabupaten'],
            // Nusa Tenggara Timur
            ['Kupang', 'Nusa Tenggara Timur', 'kota'], ['Ende', 'Nusa Tenggara Timur', 'kabupaten'],
            ['Sikka', 'Nusa Tenggara Timur', 'kabupaten'], ['Manggarai', 'Nusa Tenggara Timur', 'kabupaten'],
            // Kalimantan Barat
            ['Pontianak', 'Kalimantan Barat', 'kota'], ['Singkawang', 'Kalimantan Barat', 'kota'],
            ['Mempawah', 'Kalimantan Barat', 'kabupaten'],
            // Kalimantan Tengah
            ['Palangka Raya', 'Kalimantan Tengah', 'kota'],
            ['Kotawaringin Timur', 'Kalimantan Tengah', 'kabupaten'],
            ['Kotawaringin Barat', 'Kalimantan Tengah', 'kabupaten'],
            // Kalimantan Selatan
            ['Banjarmasin', 'Kalimantan Selatan', 'kota'], ['Banjarbaru', 'Kalimantan Selatan', 'kota'],
            ['Banjar', 'Kalimantan Selatan', 'kabupaten'],
            // Kalimantan Timur
            ['Samarinda', 'Kalimantan Timur', 'kota'], ['Balikpapan', 'Kalimantan Timur', 'kota'],
            ['Bontang', 'Kalimantan Timur', 'kota'], ['Kutai Kartanegara', 'Kalimantan Timur', 'kabupaten'],
            // Kalimantan Utara
            ['Tarakan', 'Kalimantan Utara', 'kota'], ['Bulungan', 'Kalimantan Utara', 'kabupaten'],
            // Sulawesi Utara
            ['Manado', 'Sulawesi Utara', 'kota'], ['Bitung', 'Sulawesi Utara', 'kota'],
            ['Tomohon', 'Sulawesi Utara', 'kota'], ['Kotamobagu', 'Sulawesi Utara', 'kota'],
            // Gorontalo
            ['Gorontalo', 'Gorontalo', 'kota'], ['Kabupaten Gorontalo', 'Gorontalo', 'kabupaten'],
            // Sulawesi Tengah
            ['Palu', 'Sulawesi Tengah', 'kota'], ['Banggai', 'Sulawesi Tengah', 'kabupaten'],
            ['Poso', 'Sulawesi Tengah', 'kabupaten'],
            // Sulawesi Barat
            ['Mamuju', 'Sulawesi Barat', 'kabupaten'], ['Polewali Mandar', 'Sulawesi Barat', 'kabupaten'],
            // Sulawesi Selatan
            ['Makassar', 'Sulawesi Selatan', 'kota'], ['Parepare', 'Sulawesi Selatan', 'kota'],
            ['Palopo', 'Sulawesi Selatan', 'kota'], ['Gowa', 'Sulawesi Selatan', 'kabupaten'],
            ['Bone', 'Sulawesi Selatan', 'kabupaten'],
            // Sulawesi Tenggara
            ['Kendari', 'Sulawesi Tenggara', 'kota'], ['Baubau', 'Sulawesi Tenggara', 'kota'],
            ['Kolaka', 'Sulawesi Tenggara', 'kabupaten'],
            // Maluku
            ['Ambon', 'Maluku', 'kota'], ['Tual', 'Maluku', 'kota'],
            // Maluku Utara
            ['Ternate', 'Maluku Utara', 'kota'], ['Tidore Kepulauan', 'Maluku Utara', 'kota'],
            // Papua
            ['Jayapura', 'Papua', 'kota'], ['Merauke', 'Papua', 'kabupaten'],
            ['Mimika', 'Papua', 'kabupaten'],
            // Papua Barat
            ['Manokwari', 'Papua Barat', 'kabupaten'], ['Fakfak', 'Papua Barat', 'kabupaten'],
            // Papua Barat Daya
            ['Sorong', 'Papua Barat Daya', 'kota'], ['Raja Ampat', 'Papua Barat Daya', 'kabupaten'],
            // Papua Tengah
            ['Nabire', 'Papua Tengah', 'kabupaten'],
            // Papua Pegunungan
            ['Jayawijaya', 'Papua Pegunungan', 'kabupaten'],
            // Papua Selatan
            ['Boven Digoel', 'Papua Selatan', 'kabupaten'],
        ];

        City::query()->insert(array_map(
            static fn (array $row, int $index): array => [
                'name' => $row[0],
                'province' => $row[1],
                'type' => $row[2],
                'sort_order' => $index,
            ],
            $rows,
            array_keys($rows),
        ));
    }
}
