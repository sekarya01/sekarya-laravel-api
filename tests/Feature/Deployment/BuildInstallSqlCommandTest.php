<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `sekarya:build-install-sql` — perintah yang MEMBUAT berkas pemasangan.
 *
 * `InstallSchemaTest` menjaga berkas yang sudah di-commit. Kelas ini menjaga
 * hal yang berbeda: perintah yang menghasilkannya. Keduanya diperlukan, karena
 * berkas yang benar hari ini tidak menjamin regenerasi berikutnya juga benar —
 * dan yang meregenerasi biasanya sedang buru-buru memperbaiki impor yang gagal
 * di mesin orang lain.
 *
 * Setiap test di sini menulis ke path sementara. Perintah ini secara bawaan
 * menimpa `database/schema/sekarya-install.sql`, dan test tidak boleh menyentuh
 * berkas yang di-commit.
 */
final class BuildInstallSqlCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $written = [];

    protected function tearDown(): void
    {
        foreach ($this->written as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->written = [];

        parent::tearDown();
    }

    /** Path absolut sementara — sekaligus menguji cabang path absolut. */
    private function tempPath(string $name = 'install'): string
    {
        $path = sys_get_temp_dir().'/sekarya-'.$name.'-'.getmypid().'.sql';
        $this->written[] = $path;

        return $path;
    }

    /** @param array<string, string> $options */
    private function build(array $options = []): string
    {
        $path = $options['--path'] ?? $this->tempPath();
        $options['--path'] = $path;

        $this->artisan('sekarya:build-install-sql', $options)->assertSuccessful();

        $absolute = str_starts_with($path, '/') ? $path : base_path($path);

        $this->assertFileExists($absolute, 'perintah tidak menulis berkas apa pun');

        return (string) file_get_contents($absolute);
    }

    /**
     * Pernyataan SQL yang benar-benar dieksekusi — komentar dibuang.
     *
     * @return list<string>
     */
    private function statements(string $sql): array
    {
        $lines = array_filter(
            explode("\n", $sql),
            static fn (string $l): bool => ! str_starts_with(ltrim($l), '--') && trim($l) !== '',
        );

        $out = [];

        foreach (explode(';', implode("\n", $lines)) as $chunk) {
            $chunk = trim($chunk);

            if ($chunk !== '') {
                $out[] = $chunk;
            }
        }

        return $out;
    }

    public function test_it_writes_the_file_to_a_relative_path_under_the_project_root(): void
    {
        $relative = 'storage/framework/testing/install-relatif.sql';
        $this->written[] = base_path($relative);

        $sql = $this->build(['--path' => $relative]);

        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS', $sql);
    }

    /**
     * Path absolut dihormati apa adanya.
     *
     * `base_path()` polos menempelkan akar proyek di depan path absolut, jadi
     * `--path=/tmp/x.sql` menulis ke `<proyek>/tmp/x.sql` — berkasnya "berhasil
     * dibuat" di tempat yang tidak diminta, dan yang diminta tidak pernah ada.
     */
    public function test_an_absolute_path_is_honoured_as_given(): void
    {
        $absolute = $this->tempPath('absolut');

        $this->artisan('sekarya:build-install-sql', ['--path' => $absolute])
            ->assertSuccessful();

        $this->assertFileExists($absolute, 'path absolut tidak dihormati');
        $this->assertFileDoesNotExist(
            base_path(ltrim($absolute, '/')),
            'path absolut ditempeli akar proyek — berkasnya ditulis ke tempat yang salah',
        );
    }

    public function test_by_default_the_file_names_no_database(): void
    {
        $sql = $this->build();

        foreach ($this->statements($sql) as $statement) {
            $this->assertDoesNotMatchRegularExpression(
                '/^\s*(USE|CREATE DATABASE)\b/i',
                $statement,
                'berkas bawaan tidak boleh memilih basis data: '.$statement,
            );
        }
    }

    /**
     * `--use-database` harus menjadi pernyataan PERTAMA.
     *
     * Kalau tidak, MySQL menjawab `#1046 - No database selected` pada pernyataan
     * mana pun yang kebetulan lebih dulu — kegagalan yang menyesatkan, karena
     * yang tampak salah adalah pernyataan itu, bukan tujuan yang belum disebut.
     */
    public function test_use_database_is_the_very_first_statement(): void
    {
        $sql = $this->build(['--use-database' => 'akun_sekarya']);

        $this->assertSame(
            'USE `akun_sekarya`',
            $this->statements($sql)[0],
            'USE harus pernyataan pertama',
        );
        $this->assertStringNotContainsStringIgnoringCase(
            'CREATE DATABASE',
            $sql,
            'CREATE DATABASE gagal #1044 di akun tanpa hak CREATE, walau basis datanya sudah ada',
        );
    }

    public function test_with_database_creates_then_selects_it(): void
    {
        $sql = $this->build(['--with-database' => 'basis_sendiri']);
        $statements = $this->statements($sql);

        $this->assertStringContainsString('CREATE DATABASE IF NOT EXISTS `basis_sendiri`', $statements[0]);
        $this->assertStringContainsString('utf8mb4', $statements[0]);
        $this->assertSame('USE `basis_sendiri`', $statements[1]);
    }

    /**
     * Berkas ini dibagikan dan masuk riwayat git yang tidak bisa ditarik ulang.
     * Ia tidak boleh memuat data siapa pun, sekalipun basis datanya penuh.
     */
    public function test_it_never_emits_user_data(): void
    {
        $this->seedReference();

        $user = User::factory()->create([
            'name' => 'Nama Sangat Khas Untuk Test',
            'email' => 'jangan.pernah.muncul@contoh.test',
        ]);
        Task::factory()->for($user, 'poster')->for($this->anyCategory())->create([
            'title' => 'Judul Tugas Yang Tidak Boleh Ikut',
        ]);

        $sql = $this->build();

        foreach ([$user->name, $user->email, 'Judul Tugas Yang Tidak Boleh Ikut'] as $needle) {
            $this->assertStringNotContainsString($needle, $sql, 'data pengguna ikut terbawa: '.$needle);
        }

        foreach (['users', 'tasks', 'bids', 'user_verifications', 'payments'] as $table) {
            $this->assertStringNotContainsString(
                'INSERT IGNORE INTO `'.$table.'`',
                $sql,
                'tabel `'.$table.'` tidak boleh punya baris INSERT',
            );
        }
    }

    public function test_the_reference_data_the_app_cannot_run_without_is_included(): void
    {
        $this->seedReference();

        $sql = $this->build();

        foreach (['categories', 'skills', 'migrations'] as $table) {
            $this->assertStringContainsString(
                'INSERT IGNORE INTO `'.$table.'`',
                $sql,
                'data acuan `'.$table.'` hilang',
            );
        }
    }

    /**
     * Tidak ada INSERT tanpa kolom, apa pun isi tabelnya.
     *
     * `INSERT IGNORE INTO x () VALUES ()` menggagalkan seluruh impor di
     * pernyataan itu. Perintahnya melewati tabel kosong untuk mencegahnya.
     *
     * CATATAN: test ini TIDAK menjamin cabang "tabel kosong" ikut dieksekusi.
     * Suite ini mencampur RefreshDatabase dan DatabaseTruncation, jadi
     * `categories` bisa berisi baris ter-commit dari kelas lain — lihat catatan
     * strategi database di `Tests\TestCase`. Yang dijaga di sini adalah
     * invariannya, bukan cabangnya.
     */
    public function test_no_insert_is_ever_written_without_columns(): void
    {
        $sql = $this->build();

        $this->assertStringNotContainsString(
            'INSERT IGNORE INTO `categories` () VALUES ()',
            $sql,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/INSERT IGNORE INTO `\w+` \(\s*\) VALUES/',
            $sql,
            'tabel kosong harus dilewati, bukan menghasilkan INSERT tanpa kolom',
        );

        // Migrasi selalu ada, jadi jalur penulisan tetap terbukti berjalan.
        $this->assertStringContainsString('INSERT IGNORE INTO `migrations`', $sql);
    }

    public function test_every_table_is_created_before_anything_references_it(): void
    {
        $sql = $this->build();

        $order = [];
        preg_match_all('/CREATE TABLE IF NOT EXISTS `([^`]+)`/', $sql, $m);
        $order = $m[1];

        $this->assertNotEmpty($order, 'tidak ada CREATE TABLE sama sekali');

        $seen = [];

        foreach (explode('CREATE TABLE IF NOT EXISTS `', $sql) as $i => $chunk) {
            if ($i === 0) {
                continue;
            }

            $table = substr($chunk, 0, (int) strpos($chunk, '`'));
            $body = substr($chunk, 0, (int) strpos($chunk, ';'));

            preg_match_all('/REFERENCES `([^`]+)`/', $body, $refs);

            foreach ($refs[1] as $target) {
                if ($target === $table) {
                    continue; // foreign key ke diri sendiri
                }

                $this->assertContains(
                    $target,
                    $seen,
                    "`{$table}` merujuk `{$target}` yang belum dibuat — impor gagal di klien "
                    .'yang tidak mematikan pemeriksaan foreign key',
                );
            }

            $seen[] = $table;
        }
    }

    public function test_it_destroys_nothing(): void
    {
        $sql = $this->build();

        foreach (['DROP TABLE', 'DROP DATABASE', 'TRUNCATE', 'DELETE FROM'] as $destructive) {
            $this->assertStringNotContainsStringIgnoringCase(
                $destructive,
                $sql,
                'berkas pemasangan tidak boleh menghapus apa pun: '.$destructive,
            );
        }
    }

    public function test_it_can_be_imported_twice(): void
    {
        $sql = $this->build();

        $creates = preg_match_all('/^CREATE TABLE /m', $sql);
        $guarded = preg_match_all('/^CREATE TABLE IF NOT EXISTS /m', $sql);

        $this->assertSame($creates, $guarded, 'ada CREATE TABLE tanpa IF NOT EXISTS');

        $inserts = preg_match_all('/^INSERT /m', $sql);
        $ignored = preg_match_all('/^INSERT IGNORE /m', $sql);

        $this->assertSame($inserts, $ignored, 'ada INSERT tanpa IGNORE');
    }

    /**
     * Pengaturan sesi ditulis POLOS.
     *
     * `mysqldump` menaruhnya di komentar bersyarat `/*!40014 ... *\/`, yang boleh
     * dilewati klien mana pun yang tidak mengurainya. phpMyAdmin melewatinya.
     */
    public function test_session_settings_cannot_be_skipped_by_a_client(): void
    {
        $sql = $this->build();

        foreach (['FOREIGN_KEY_CHECKS', 'SET NAMES utf8mb4'] as $setting) {
            $this->assertMatchesRegularExpression(
                '/^\s*SET (NAMES utf8mb4|FOREIGN_KEY_CHECKS)/mi',
                $sql,
                'pengaturan sesi harus ada sebagai pernyataan polos',
            );
            $this->assertStringNotContainsString(
                '/*!40014',
                $sql,
                'pengaturan sesi tidak boleh di dalam komentar bersyarat: '.$setting,
            );
        }
    }

    public function test_every_table_states_its_row_format(): void
    {
        $sql = $this->build();

        $engines = preg_match_all('/ENGINE=InnoDB/', $sql);
        $formats = preg_match_all('/ROW_FORMAT=DYNAMIC/', $sql);

        $this->assertSame(
            $engines,
            $formats,
            'setiap tabel harus menyebut ROW_FORMAT=DYNAMIC — tanpanya MySQL 5.7 '
            .'menolak dengan #1071 (max key length 767 bytes)',
        );
    }

    public function test_local_auto_increment_counters_are_stripped(): void
    {
        $sql = $this->build();

        $this->assertDoesNotMatchRegularExpression(
            '/AUTO_INCREMENT=\d+/',
            $sql,
            'penghitung AUTO_INCREMENT lokal tidak relevan di pemasangan baru '
            .'dan membuat diff berkas berubah tanpa alasan',
        );
    }

    public function test_two_runs_produce_an_identical_file(): void
    {
        $this->seedReference();

        $first = $this->build(['--path' => $this->tempPath('sekali')]);
        $second = $this->build(['--path' => $this->tempPath('duakali')]);

        $this->assertSame(
            $first,
            $second,
            'keluaran tidak deterministik — setiap regenerasi akan menghasilkan diff palsu',
        );
    }

    public function test_it_reports_where_it_wrote_and_refuses_to_claim_success(): void
    {
        $path = $this->tempPath('lapor');

        $this->artisan('sekarya:build-install-sql', ['--path' => $path])
            ->expectsOutputToContain('Belum selesai')
            ->assertSuccessful();

        $this->assertFileExists($path);
    }

    public function test_it_creates_the_target_directory_when_missing(): void
    {
        $dir = sys_get_temp_dir().'/sekarya-dir-'.getmypid();
        $path = $dir.'/dalam/berkas.sql';
        $this->written[] = $path;

        $this->artisan('sekarya:build-install-sql', ['--path' => $path])
            ->assertSuccessful();

        $this->assertFileExists($path);

        File::deleteDirectory($dir);
    }
}
