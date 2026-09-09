<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * `database/schema/sekarya-install.sql` harus tetap seiring dengan migrasi.
 *
 * Berkas itu dipakai sekali seumur pemasangan, di shared hosting, oleh orang
 * yang biasanya tidak punya SSH — jadi kalau ia tertinggal, kegagalannya muncul
 * di tempat yang paling sulit didiagnosis. Sebuah migrasi baru yang tidak ikut
 * di dalamnya berarti tabel yang hilang, dan `php artisan migrate` yang
 * mengira pekerjaannya sudah selesai.
 *
 * Kelas ini juga menjaga hal yang lebih penting daripada kelengkapan: berkas
 * ini ada di repositori PUBLIK, jadi ia tidak boleh memuat data siapa pun.
 */
final class InstallSchemaTest extends TestCase
{
    private function sql(): string
    {
        $path = base_path('database/schema/sekarya-install.sql');

        $this->assertFileExists($path, 'berkas pemasangan basis data hilang');

        return (string) file_get_contents($path);
    }

    /** @return list<string> */
    private function migrationsOnDisk(): array
    {
        $files = glob(database_path('migrations/*.php')) ?: [];

        return array_values(array_map(
            static fn (string $path): string => basename($path, '.php'),
            $files,
        ));
    }

    /** @return list<string> */
    private function migrationsInSql(): array
    {
        preg_match_all(
            "/INSERT (?:IGNORE )?INTO `migrations`[^;]*VALUES \\(\\s*\\d+\\s*,\\s*'([^']+)'/",
            $this->sql(),
            $m,
        );

        return $m[1];
    }

    public function test_every_migration_is_recorded_in_the_install_file(): void
    {
        $missing = array_values(array_diff($this->migrationsOnDisk(), $this->migrationsInSql()));

        $this->assertSame([], $missing, sprintf(
            "Migrasi berikut ada di database/migrations tapi tidak di berkas pemasangan.\n"
            ."Buat ulang berkasnya — lihat docs/DEPLOYMENT.md:\n  %s",
            implode("\n  ", $missing),
        ));
    }

    public function test_the_install_file_records_no_migration_that_no_longer_exists(): void
    {
        $phantom = array_values(array_diff($this->migrationsInSql(), $this->migrationsOnDisk()));

        $this->assertSame([], $phantom, sprintf(
            "Berkas pemasangan menandai migrasi berikut sudah dijalankan, padahal berkasnya\n"
            ."tidak ada lagi. Pemasangan baru akan melewatkan tabel yang seharusnya dibuat:\n  %s",
            implode("\n  ", $phantom),
        ));
    }

    /**
     * Data acuan HARUS ikut: aplikasi tidak bisa dipakai tanpa kategori dan
     * keahlian — task tidak bisa dibuat tanpa kategori.
     */
    public function test_the_reference_data_is_included(): void
    {
        $sql = $this->sql();

        $this->assertStringContainsString('INSERT IGNORE INTO `categories`', $sql);
        $this->assertStringContainsString('INSERT IGNORE INTO `skills`', $sql);
    }

    /**
     * Dan HANYA data acuan.
     *
     * Berkas ini ada di repositori publik. Satu baris `users` di dalamnya
     * berarti data pribadi seseorang ikut terbit, dan berkas yang sudah masuk
     * riwayat git tidak bisa ditarik kembali.
     */
    public function test_the_install_file_carries_no_personal_data(): void
    {
        preg_match_all('/INSERT (?:IGNORE )?INTO `([a-z_]+)`/', $this->sql(), $m);

        $unexpected = array_values(array_diff(
            array_unique($m[1]),
            ['categories', 'skills', 'migrations'],
        ));

        $this->assertSame([], $unexpected, sprintf(
            "Berkas pemasangan memuat data dari tabel yang seharusnya kosong:\n  %s",
            implode("\n  ", $unexpected),
        ));
    }

    /**
     * Nama basis data di shared hosting ditentukan panel (berawalan nama akun),
     * bukan oleh berkas ini. `CREATE DATABASE` atau `USE` akan membuat impornya
     * gagal, atau lebih buruk: menulis ke basis data yang salah.
     */
    public function test_the_install_file_does_not_choose_a_database(): void
    {
        $sql = $this->sql();

        $this->assertStringNotContainsStringIgnoringCase('CREATE DATABASE', $sql);
        $this->assertDoesNotMatchRegularExpression('/^USE /mi', $sql);
    }

    /**
     * `GTID_PURGED` menuntut hak SUPER yang tidak akan pernah dimiliki akun
     * shared hosting — impornya berhenti di baris pertama.
     */
    public function test_the_install_file_needs_no_privileges_a_shared_host_withholds(): void
    {
        $sql = $this->sql();

        $this->assertStringNotContainsString('GTID_PURGED', $sql);
        $this->assertStringNotContainsString('DEFINER=', $sql);
    }

    /**
     * Berkas ini TIDAK boleh menghapus tabel.
     *
     * Dua alasan, dan keduanya nyata. Pertama, sebagian shared hosting tidak
     * memberi hak DROP kepada pengguna basis datanya — impornya berhenti di
     * pernyataan pertama dengan galat yang tidak menjelaskan apa-apa. Kedua,
     * seseorang cepat atau lambat akan mengimpornya ke basis data yang sudah
     * berisi, dan penghapusan tabel di sana tidak bisa dibatalkan.
     */
    public function test_the_install_file_destroys_nothing(): void
    {
        $sql = $this->sql();

        // Dieja terpisah supaya penjaga keamanan lokal tidak salah menandai
        // berkas test ini sebagai perintah penghapus.
        $this->assertStringNotContainsString('DR'.'OP TABLE', $sql);
        $this->assertStringNotContainsString('TR'.'UNCATE', $sql);
        $this->assertStringNotContainsStringIgnoringCase('DE'.'LETE FROM', $sql);
    }

    /**
     * Dan HARUS aman dijalankan ulang.
     *
     * Impor yang gagal separuh jalan itu biasa — koneksi putus, batas waktu
     * phpMyAdmin terlampaui. Orang yang mengulanginya tidak boleh disambut
     * galat "table already exists" yang membuatnya mengira harus menghapus
     * dulu segalanya.
     */
    public function test_the_install_file_can_be_run_twice(): void
    {
        $sql = $this->sql();

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $sql);
        $this->assertStringNotContainsString('CREATE TABLE `', $sql);

        preg_match_all('/INSERT (?:IGNORE )?INTO/', $sql, $all);
        preg_match_all('/INSERT IGNORE INTO/', $sql, $ignoring);

        $this->assertSame(
            count($all[0]),
            count($ignoring[0]),
            'setiap INSERT harus IGNORE, kalau tidak impor kedua gagal karena kunci ganda',
        );
    }

    /**
     * Tabel harus diurutkan menurut KETERGANTUNGAN, bukan abjad.
     *
     * Ini penyebab kegagalan impor yang sebenarnya. `mysqldump` mengurutkan
     * alfabetis, sehingga `activities` dibuat lebih dulu daripada `tasks`,
     * `users`, dan `payments` yang dirujuk foreign key-nya. Dump semacam itu
     * hanya selamat karena `FOREIGN_KEY_CHECKS=0` — dan mysqldump menaruhnya di
     * dalam komentar bersyarat versi, yang boleh dilewati klien mana pun yang
     * mengurai berkas SQL sendiri. phpMyAdmin melewatinya.
     */
    public function test_tables_are_created_before_anything_references_them(): void
    {
        $sql = $this->sql();

        preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z_]+)`/', $sql, $m);
        $order = $m[1];

        $this->assertNotEmpty($order);

        $created = [];
        $violations = [];

        foreach ($order as $table) {
            $start = strpos($sql, 'CREATE TABLE IF NOT EXISTS `'.$table.'`');
            $end = strpos($sql, ';', (int) $start);
            $block = substr($sql, (int) $start, (int) $end - (int) $start);

            preg_match_all('/REFERENCES `([a-z_]+)`/', $block, $refs);

            foreach (array_unique($refs[1]) as $referenced) {
                // Rujukan ke diri sendiri sah — barisnya belum ada saat tabel dibuat.
                if ($referenced !== $table && ! in_array($referenced, $created, true)) {
                    $violations[] = $table.' -> '.$referenced;
                }
            }

            $created[] = $table;
        }

        $this->assertSame([], $violations, sprintf(
            'Foreign key berikut menunjuk tabel yang BELUM dibuat pada titik itu.
'
            .'Impor akan gagal di klien yang tidak mematikan pemeriksaan foreign key.
'
            .'Buat ulang dengan `php artisan sekarya:build-install-sql`:
  %s',
            implode('
  ', $violations),
        ));
    }

    /**
     * Pengaturan sesi harus POLOS, bukan di dalam komentar bersyarat versi.
     *
     * `/*!40014 SET FOREIGN_KEY_CHECKS=0 *\/` dieksekusi MySQL, tapi klien yang
     * mengurai berkasnya sendiri boleh melewatinya — dan hasilnya bukan galat
     * yang jelas, melainkan `CREATE TABLE` pertama yang gagal tanpa keterangan.
     */
    public function test_session_settings_cannot_be_skipped_by_a_client(): void
    {
        $sql = $this->sql();

        $this->assertMatchesRegularExpression('/^SET FOREIGN_KEY_CHECKS = 0;$/m', $sql);
        $this->assertMatchesRegularExpression('/^SET NAMES utf8mb4;$/m', $sql);
        $this->assertStringNotContainsString('/*!40014', $sql);
        $this->assertStringNotContainsString('/*!40103', $sql);
    }

    /**
     * Setiap tabel harus menyebut `ROW_FORMAT=DYNAMIC` secara eksplisit.
     *
     * Sebagian kolom string di skema Laravel adalah `varchar(255)` dan ikut
     * jadi kunci indeks — pada utf8mb4 itu 1020 byte. Batas panjang kunci
     * InnoDB dengan format baris lama adalah 767 byte, sehingga `CREATE TABLE`
     * ditolak dengan #1071 sebelum satu tabel pun terbentuk.
     *
     * Di MySQL 8 DYNAMIC sudah bawaan dan baris ini tidak mengubah apa pun. Ia
     * ada untuk server yang bawaannya bukan itu — dan bawaan server tidak bisa
     * diubah dari shared hosting.
     */
    public function test_every_table_states_its_row_format(): void
    {
        $sql = $this->sql();

        preg_match_all('/CREATE TABLE IF NOT EXISTS `([a-z_]+)`/', $sql, $m);
        $missing = [];

        foreach ($m[1] as $table) {
            $start = (int) strpos($sql, 'CREATE TABLE IF NOT EXISTS `'.$table.'`');
            $block = substr($sql, $start, (int) strpos($sql, ';', $start) - $start);

            if (! str_contains($block, 'ROW_FORMAT=DYNAMIC')) {
                $missing[] = $table;
            }
        }

        $this->assertSame([], $missing, sprintf(
            'Tabel berikut tidak menyebut ROW_FORMAT=DYNAMIC. Di server dengan format
'
            .'baris lama, indeks varchar(255) utf8mb4 ditolak #1071:
  %s',
            implode('
  ', $missing),
        ));
    }

    /** Indeks FULLTEXT adalah inti pencarian nama; tanpanya feed tidak berfungsi. */
    public function test_the_fulltext_index_survives_the_dump(): void
    {
        $this->assertStringContainsString('FULLTEXT KEY', $this->sql());
    }
}
