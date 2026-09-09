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
            "/INSERT INTO `migrations`[^;]*VALUES \(\d+,'([^']+)'/",
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

        $this->assertStringContainsString('INSERT INTO `categories`', $sql);
        $this->assertStringContainsString('INSERT INTO `skills`', $sql);
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
        preg_match_all('/INSERT INTO `([a-z_]+)`/', $this->sql(), $m);

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

    /** Indeks FULLTEXT adalah inti pencarian nama; tanpanya feed tidak berfungsi. */
    public function test_the_fulltext_index_survives_the_dump(): void
    {
        $this->assertStringContainsString('FULLTEXT KEY', $this->sql());
    }
}
