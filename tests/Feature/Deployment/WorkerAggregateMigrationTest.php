<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Perpindahan reputasi pekerja `users` -> `user_workers`, DENGAN DATA.
 *
 * Migrasi yang memindahkan data tidak bisa dibuktikan oleh suite biasa: setiap
 * test berjalan di atas basis data yang baru saja dimigrasikan penuh, jadi
 * tidak pernah ada baris lama untuk dipindahkan. Yang gagal di produksi justru
 * itu — basis data yang sudah berisi orang.
 *
 * Kelas ini membangun keadaan sungguhan di basis data sekali-pakai: mundur ke
 * skema sebelum perpindahan, menulis reputasi di kolom lamanya, lalu maju.
 *
 * Jalur BALIK ikut diuji, dan itu bukan kelengkapan: `down()` di sini pernah
 * benar-benar rusak. Ia memakai `after('skills')` — kolom yang sudah dihapus
 * ketika keahlian menjadi relasi — sehingga seluruh rollback berhenti dengan
 * "Unknown column 'skills'". Tidak ada satu pun test yang menyentuhnya, dan
 * `down()` yang tidak bisa dijalankan baru ketahuan pada saat ia paling
 * dibutuhkan: rollback di tengah insiden produksi.
 */
final class WorkerAggregateMigrationTest extends TestCase
{
    /** Migrasi yang dipasang paling akhir, yang harus dimundurkan bersama. */
    private const int STEPS = 3;

    private string $database = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Nama unik: suite ini mencampur RefreshDatabase dengan
        // DatabaseTruncation, jadi sisa dari jalannya yang lalu bisa masih ada.
        $name = 'sekarya_migrationcheck_'.substr(md5((string) mt_rand()), 0, 8);

        try {
            Schema::createDatabase($name);
        } catch (\Throwable $e) {
            $this->markTestSkipped('tidak punya hak membuat basis data: '.$e->getMessage());
        }

        $this->database = $name;
    }

    protected function tearDown(): void
    {
        if ($this->database !== '') {
            Schema::dropDatabaseIfExists($this->database);
        }

        parent::tearDown();
    }

    /** Artisan di basis data sekali-pakai, lewat proses terpisah. */
    private function runArtisan(string $command): void
    {
        $result = Process::path(base_path())
            ->env(['DB_DATABASE' => $this->database])
            ->timeout(180)
            ->run('php artisan '.$command.' --force');

        $this->assertTrue($result->successful(), sprintf(
            "`php artisan %s` gagal di basis data uji:\n%s\n%s",
            $command,
            $result->output(),
            $result->errorOutput(),
        ));
    }

    /**
     * Satu baris milik seseorang, dicari lewat alamat emailnya.
     *
     * @return array<string, mixed>|null
     */
    private function row(string $table, string $email): ?array
    {
        $row = DB::selectOne(sprintf(
            'SELECT t.* FROM `%1$s`.`%2$s` t JOIN `%1$s`.`users` u ON u.id = %3$s WHERE u.email = ?',
            $this->database,
            $table,
            $table === 'users' ? 't.id' : 't.user_id',
        ), [$email]);

        return $row === null ? null : (array) $row;
    }

    private function seedLegacyUsers(): void
    {
        DB::table($this->database.'.users')->insert([
            [
                'ulid' => '01MIGRATIONCHECK0000000001',
                'name' => 'Pekerja Lama',
                'email' => 'lama@sekarya.test',
                'phone' => '+628110000001',
                'password' => 'x',
                'active_mode' => 'working',
                'status' => 'active',
                'theme' => 'system',
                'worker_rating_avg' => 4.75,
                'worker_rating_count' => 12,
                'tasks_completed' => 40,
                'bids_won' => 55,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'ulid' => '01MIGRATIONCHECK0000000002',
                'name' => 'Belum Pernah Bekerja',
                'email' => 'baru@sekarya.test',
                'phone' => '+628110000002',
                'password' => 'x',
                'active_mode' => 'hiring',
                'status' => 'active',
                'theme' => 'system',
                // Nol, bukan dihilangkan: insert massal menuntut kunci yang
                // sama persis di setiap baris.
                'worker_rating_avg' => 0,
                'worker_rating_count' => 0,
                'tasks_completed' => 0,
                'bids_won' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_worker_reputation_survives_the_move_in_both_directions(): void
    {
        // Maju penuh, lalu mundur ke skema SEBELUM perpindahan.
        $this->runArtisan('migrate');
        $this->runArtisan('migrate:rollback --step='.self::STEPS);

        $this->seedLegacyUsers();

        // ── maju: reputasi pindah utuh ──────────────────────────────────────
        $this->runArtisan('migrate');

        $moved = $this->row('user_workers', 'lama@sekarya.test');

        $this->assertNotNull($moved, 'reputasi pekerja lama tidak ikut pindah');
        $this->assertSame('4.75', (string) $moved['worker_rating_avg']);
        $this->assertSame(12, (int) $moved['worker_rating_count']);
        $this->assertSame(40, (int) $moved['tasks_completed']);
        $this->assertSame(55, (int) $moved['bids_won']);

        // Yang belum pernah bekerja TIDAK dapat baris. Profilnya lahir saat
        // orangnya mulai bekerja — bukan puluhan ribu baris nol yang tidak
        // menjawab pertanyaan apa pun.
        $this->assertNull($this->row('user_workers', 'baru@sekarya.test'));

        // ── mundur: reputasi kembali ke kolom lamanya ───────────────────────
        $this->runArtisan('migrate:rollback --step='.self::STEPS);

        $back = $this->row('users', 'lama@sekarya.test');

        $this->assertNotNull($back);
        $this->assertSame('4.75', (string) $back['worker_rating_avg']);
        $this->assertSame(12, (int) $back['worker_rating_count']);
        $this->assertSame(40, (int) $back['tasks_completed']);
        $this->assertSame(55, (int) $back['bids_won']);
    }
}
