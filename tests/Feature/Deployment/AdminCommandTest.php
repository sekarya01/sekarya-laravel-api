<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * `php artisan sekarya:admin` — satu-satunya cara akun super_admin lahir.
 *
 * Diuji sebagai berkas Deployment, bukan sebagai test Console biasa, karena
 * inilah langkah pemasangan yang dijalankan orang di server: kalau ia gagal,
 * ia gagal di tempat yang paling sulit didiagnosis, dan aplikasinya tidak
 * punya satu pun pengelola.
 */
final class AdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const string STRONG = 'RahasiaKuatSekali99!';

    public function test_it_creates_the_super_admin(): void
    {
        $this->artisan('sekarya:admin', [
            'action' => 'create',
            '--name' => 'Super Admin',
            '--email' => 'Super@Sekarya.test',
            '--password' => self::STRONG,
        ])->assertSuccessful();

        $admin = Admin::query()->sole();

        $this->assertSame(AdminRole::SuperAdmin, $admin->role);
        $this->assertSame(AdminStatus::Active, $admin->status);
        // Dinormalkan, kalau tidak login dengan alamat huruf kecil tidak cocok.
        $this->assertSame('super@sekarya.test', $admin->email);
        $this->assertTrue(Hash::check(self::STRONG, (string) $admin->password));
    }

    /** Sandinya tidak boleh muncul di keluaran terminal maupun di riwayat. */
    public function test_it_never_prints_the_password_back(): void
    {
        $this->artisan('sekarya:admin', [
            'action' => 'create',
            '--name' => 'Super Admin',
            '--email' => 'super@sekarya.test',
            '--password' => self::STRONG,
        ])
            ->doesntExpectOutputToContain(self::STRONG)
            ->assertSuccessful();
    }

    public function test_a_second_super_admin_is_refused_with_an_explanation(): void
    {
        $this->superAdmin(['email' => 'super@sekarya.test']);

        $this->artisan('sekarya:admin', [
            'action' => 'create',
            '--name' => 'Dua',
            '--email' => 'super2@sekarya.test',
            '--password' => self::STRONG,
        ])->assertFailed();

        $this->assertSame(1, Admin::query()->where('role', AdminRole::SuperAdmin)->count());
        $this->assertDatabaseMissing('admins', ['email' => 'super2@sekarya.test']);
    }

    public function test_it_creates_plain_admins_too(): void
    {
        $this->artisan('sekarya:admin', [
            'action' => 'create',
            '--role' => 'admin',
            '--name' => 'Verifikator',
            '--email' => 'verif@sekarya.test',
            '--password' => self::STRONG,
        ])->assertSuccessful();

        $this->assertSame(
            AdminRole::Admin,
            Admin::query()->where('email', 'verif@sekarya.test')->sole()->role,
        );
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $this->artisan('sekarya:admin', [
            'action' => 'create',
            '--role' => 'dewa',
            '--name' => 'X',
            '--email' => 'x@sekarya.test',
            '--password' => self::STRONG,
        ])->assertFailed();

        $this->assertDatabaseMissing('admins', ['email' => 'x@sekarya.test']);
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->artisan('sekarya:admin', [
            'action' => 'create',
            '--name' => 'X',
            '--email' => 'x@sekarya.test',
            '--password' => 'pendek',
        ])->assertFailed();

        $this->assertDatabaseMissing('admins', ['email' => 'x@sekarya.test']);
    }

    public function test_a_duplicate_address_is_refused(): void
    {
        $this->activeAdmin(['email' => 'sudah@sekarya.test']);

        $this->artisan('sekarya:admin', [
            'action' => 'create',
            '--role' => 'admin',
            '--name' => 'Kembar',
            '--email' => 'sudah@sekarya.test',
            '--password' => self::STRONG,
        ])->assertFailed();
    }

    /**
     * Sandi contoh yang ikut terlacak git DITOLAK di produksi.
     *
     * Di laptop ia diizinkan (dengan peringatan), karena itulah yang membuat
     * `bash docs/smoke.sh` bisa berjalan sendiri. Di produksi ia bukan
     * kredensial — ia sandi yang bisa dibaca siapa pun yang membuka
     * repositori, pada akun paling berhak di seluruh aplikasi.
     */
    public function test_an_example_password_is_refused_in_production(): void
    {
        $example = (string) (config('sekarya.admin.forbidden_passwords')[0] ?? '');
        $this->assertNotSame('', $example);

        // Lokal: lolos dengan peringatan.
        $this->artisan('sekarya:admin', [
            'action' => 'create',
            '--role' => 'admin',
            '--name' => 'Lokal',
            '--email' => 'lokal@sekarya.test',
            '--password' => $example,
        ])->assertSuccessful();

        $this->app['env'] = 'production';

        $this->artisan('sekarya:admin', [
            'action' => 'create',
            '--role' => 'admin',
            '--name' => 'Produksi',
            '--email' => 'produksi@sekarya.test',
            '--password' => $example,
        ])->assertFailed();

        $this->assertDatabaseMissing('admins', ['email' => 'produksi@sekarya.test']);
    }

    // ── list / suspend / activate ───────────────────────────────────────────

    public function test_list_names_every_admin(): void
    {
        $this->superAdmin(['email' => 'super@sekarya.test']);
        $this->activeAdmin(['email' => 'verif@sekarya.test']);

        $this->artisan('sekarya:admin', ['action' => 'list'])
            ->expectsOutputToContain('super@sekarya.test')
            ->expectsOutputToContain('verif@sekarya.test')
            ->assertSuccessful();
    }

    public function test_suspending_an_admin_revokes_its_tokens(): void
    {
        $admin = $this->activeAdmin(['email' => 'verif@sekarya.test']);
        app(TokenIssuer::class)->issuePair($admin);
        $this->assertSame(2, $admin->tokens()->count());

        $this->artisan('sekarya:admin', [
            'action' => 'suspend',
            '--email' => 'verif@sekarya.test',
        ])->assertSuccessful();

        $this->assertSame(AdminStatus::Suspended, $admin->refresh()->status);
        // Status saja tidak menghentikan siapa pun: tokennya hidup 8 jam.
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_activating_brings_an_admin_back(): void
    {
        $admin = Admin::factory()->suspended()->create(['email' => 'verif@sekarya.test']);

        $this->artisan('sekarya:admin', [
            'action' => 'activate',
            '--email' => 'verif@sekarya.test',
        ])->assertSuccessful();

        $this->assertSame(AdminStatus::Active, $admin->refresh()->status);
    }

    /**
     * super_admin tidak bisa dinonaktifkan.
     *
     * Ia satu-satunya yang bisa membuat pengelola baru, jadi
     * menonaktifkannya berarti tidak ada lagi jalan memulihkan akses
     * pengelola dari dalam aplikasi.
     */
    public function test_the_super_admin_cannot_be_suspended(): void
    {
        $superAdmin = $this->superAdmin(['email' => 'super@sekarya.test']);

        $this->artisan('sekarya:admin', [
            'action' => 'suspend',
            '--email' => 'super@sekarya.test',
        ])->assertFailed();

        $this->assertSame(AdminStatus::Active, $superAdmin->refresh()->status);
    }

    public function test_an_unknown_address_is_refused(): void
    {
        $this->artisan('sekarya:admin', [
            'action' => 'suspend',
            '--email' => 'tidakada@sekarya.test',
        ])->assertFailed();
    }

    public function test_an_unknown_action_is_refused(): void
    {
        $this->artisan('sekarya:admin', ['action' => 'hapus-semua'])->assertFailed();
    }
}
