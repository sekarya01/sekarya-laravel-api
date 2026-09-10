<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Masuk dasbor super_admin: sesi `admin_web`, bukan token API.
 *
 * Aturannya sama seperti AdminLoginAction — dan test ini menjaganya dari
 * sisi web: hash dummy anti-enumeration tidak bisa diuji dari luar, tapi
 * penolakan admin biasa dan akun suspended bisa.
 */
final class AuthTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<string> */
    private function protectedPaths(): array
    {
        return ['/', '/verifications', '/payments', '/users', '/workers', '/admins', '/audit-logs'];
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        foreach ($this->protectedPaths() as $path) {
            $this->get('/access/super_admin'.$path)
                ->assertRedirect(route('super_admin.login'));
        }
    }

    public function test_halaman_login_tampil(): void
    {
        $this->get(route('super_admin.login'))
            ->assertOk()
            ->assertSee('Masuk ke Konsol', false);
    }

    public function test_login_berhasil_sebagai_super_admin(): void
    {
        $admin = $this->superAdmin();

        $this->post(route('super_admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('super_admin.dashboard'));

        $this->assertAuthenticatedAs($admin, 'admin_web');
        $this->get(route('super_admin.dashboard'))->assertOk();
    }

    public function test_login_gagal_dengan_sandi_salah(): void
    {
        $admin = $this->superAdmin();

        $this->post(route('super_admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'jelas-bukan-ini',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertGuest('admin_web');
    }

    public function test_admin_biasa_ditolak(): void
    {
        $admin = $this->activeAdmin();

        $this->post(route('super_admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        // Kalaupun sesinya ada (mis. dibuat manual), gerbang menendangnya.
        $this->actingAs($admin, 'admin_web');
        $this->get(route('super_admin.dashboard'))
            ->assertRedirect(route('super_admin.login'));
    }

    public function test_super_admin_suspended_tidak_bisa_login(): void
    {
        $admin = $this->superAdmin();
        $admin->status = AdminStatus::Suspended;
        $admin->save();

        $this->post(route('super_admin.login.attempt'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin_web');
    }

    public function test_logout_mencabut_sesi(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        $this->post(route('super_admin.logout'))
            ->assertRedirect(route('super_admin.login'));

        $this->assertGuest('admin_web');
    }

    public function test_sudah_masuk_dialihkan_dari_halaman_login(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        $this->get(route('super_admin.login'))
            ->assertRedirect(route('super_admin.dashboard'));
    }

    public function test_tamu_json_mendapat_401(): void
    {
        $this->getJson(route('super_admin.dashboard'))
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Belum masuk sebagai pengelola.');
    }

    public function test_admin_biasa_json_mendapat_403(): void
    {
        $this->actingAs($this->activeAdmin(), 'admin_web');

        $this->getJson(route('super_admin.dashboard'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Hanya super_admin yang boleh membuka dasbor ini.');
    }
}
