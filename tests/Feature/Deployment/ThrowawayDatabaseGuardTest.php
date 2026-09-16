<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use ReflectionMethod;
use RuntimeException;
use Tests\TestCase;

/**
 * Pagar di depan pembuangan basis data, diuji sendiri.
 *
 * `WorkerAggregateMigrationTest` adalah SATU-SATUNYA tempat di proyek ini yang
 * membuat dan membuang basis data — skema selebihnya hanya berubah lewat
 * migrasi. Pengecualian itu perlu karena migrasi yang memindahkan data tidak
 * bisa dibuktikan di atas basis data yang baru saja dimigrasikan penuh.
 *
 * Yang tidak boleh terjadi: pagarnya dilonggarkan, dan tidak ada yang tahu.
 * Pagar yang tidak diuji adalah niat baik, bukan penjaga — dan kegagalannya
 * hanya terlihat sesudah basis data yang salah hilang.
 *
 * Kelas ini sengaja memanggil pagarnya lewat refleksi alih-alih menjalankan
 * test aslinya: yang diuji di sini PENOLAKANNYA, dan satu-satunya cara
 * membuktikan sebuah penolakan bekerja adalah memberinya nama yang memang
 * harus ditolak.
 */
final class ThrowawayDatabaseGuardTest extends TestCase
{
    private function guard(string $name): void
    {
        $method = new ReflectionMethod(WorkerAggregateMigrationTest::class, 'assertThrowaway');

        $method->invoke($this->newMigrationTest(), $name);
    }

    private function newMigrationTest(): WorkerAggregateMigrationTest
    {
        // Tanpa setUp(): kita hanya butuh instansnya untuk memanggil pagar,
        // dan setUp() justru akan membuat basis data sungguhan.
        return new WorkerAggregateMigrationTest('assertThrowaway');
    }

    public function test_a_name_with_the_throwaway_prefix_passes(): void
    {
        $this->guard('sekarya_migrationcheck_deadbeef');

        // Tidak melempar = lolos. Assertion eksplisit supaya test ini tidak
        // dihitung "tanpa assertion" dan diam-diam berhenti berarti.
        $this->assertTrue(true);
    }

    /**
     * Nama tanpa awalan itu ditolak — termasuk nama basis data pengembangan
     * yang wajar seperti `sekarya`.
     */
    public function test_a_name_without_the_prefix_is_refused(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Menolak menyentuh basis data "sekarya"');

        $this->guard('sekarya');
    }

    /** Termasuk yang HAMPIR benar — awalan harus persis, bukan mengandung. */
    public function test_a_name_that_merely_contains_the_prefix_is_refused(): void
    {
        $this->expectException(RuntimeException::class);

        $this->guard('produksi_sekarya_migrationcheck_1');
    }

    /**
     * Lapis kedua: basis data yang sedang dipakai koneksi tidak boleh dibuang
     * walaupun namanya kebetulan lolos lapis pertama.
     *
     * Inilah yang menutup kejadian paling mungkin — seseorang menjalankan
     * suite dengan `DB_DATABASE` yang menunjuk basis data sungguhan.
     */
    public function test_the_database_in_use_is_refused_even_with_the_right_prefix(): void
    {
        $connection = (string) config('database.default');
        $trap = 'sekarya_migrationcheck_inuse';

        config(['database.connections.'.$connection.'.database' => $trap]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('yang sedang dipakai koneksi');

        $this->guard($trap);
    }

    /** Dan basis data test yang sedang berjalan pun tidak kebal. */
    public function test_the_live_test_database_is_never_a_valid_target(): void
    {
        $inUse = (string) config(
            'database.connections.'.config('database.default').'.database',
        );

        $this->expectException(RuntimeException::class);

        $this->guard($inUse);
    }
}
