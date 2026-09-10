<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Enums\Gender;
use App\Enums\UserStatus;
use App\Models\UserWorker;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Moderasi pengguna lewat web: tombol kondisional per status, popup
 * terbuka lagi saat validasi gagal, token ikut dicabut.
 */
final class UserModerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_dan_cari_email_persis(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser(['email' => 'carisaya@sekarya.test']);

        $this->get(route('super_admin.users.index'))
            ->assertOk()
            ->assertSee($user->email);

        $this->get(route('super_admin.users.index', ['email' => 'carisaya@sekarya.test']))
            ->assertOk()
            ->assertSee($user->email);
    }

    public function test_detail_menampilkan_identitas_baru(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();

        $this->assertNotNull($user->gender);
        $this->assertNotNull($user->birth_date);

        $this->get(route('super_admin.users.show', $user->ulid))
            ->assertOk()
            ->assertSee($user->gender->label(), false)
            ->assertSee($user->birth_date->toDateString(), false);
    }

    public function test_akun_aktif_hanya_tangguhkan_dan_blokir(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();

        $this->get(route('super_admin.users.show', $user->ulid))
            ->assertOk()
            ->assertSee('Tangguhkan', false)
            ->assertSee('Blokir', false)
            ->assertDontSee('Pulihkan', false);
    }

    public function test_akun_suspended_tanpa_tangguhkan(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->get(route('super_admin.users.show', $user->ulid))
            ->assertOk()
            ->assertSee('Pulihkan', false)
            ->assertSee('Blokir', false)
            ->assertDontSee('Tangguhkan', false);
    }

    public function test_menangguhkan_mencabut_token(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin_web');
        $user = $this->activeUser();
        app(TokenIssuer::class)->issuePair($user);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'tokenable_type' => $user::class,
        ]);

        $this->post(route('super_admin.users.suspend', $user->ulid), [
            'reason' => 'Melaporkan transfer palsu dua kali berturut-turut.',
        ])->assertSessionHas('status');

        $this->assertSame(UserStatus::Suspended, $user->refresh()->status);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->getKey(),
            'tokenable_type' => $user::class,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'user.suspended',
            'subject_id' => $user->getKey(),
        ]);
    }

    public function test_alasan_pendek_membuka_lagi_popup_yang_benar(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();

        $this->post(route('super_admin.users.suspend', $user->ulid), ['reason' => 'x'])
            ->assertSessionHasErrors('reason');

        $this->assertSame('suspend', session('open_modal'));
        $this->assertSame(UserStatus::Active, $user->refresh()->status);
    }

    public function test_memblokir_dan_memulihkan(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();

        $this->post(route('super_admin.users.ban', $user->ulid), [
            'reason' => 'Terbukti memakai NIK milik orang lain.',
        ])->assertSessionHas('status');

        $this->assertSame(UserStatus::Banned, $user->refresh()->status);

        $this->post(route('super_admin.users.reinstate', $user->ulid))
            ->assertSessionHas('status');

        $this->assertSame(UserStatus::Active, $user->refresh()->status);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'user.reinstated',
            'subject_id' => $user->getKey(),
        ]);
    }

    public function test_memulihkan_akun_aktif_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();

        $this->post(route('super_admin.users.reinstate', $user->ulid))
            ->assertSessionHasErrors('action');

        $this->assertSame('reinstate', session('open_modal'));
        $this->assertSame(UserStatus::Active, $user->refresh()->status);
    }

    public function test_menangguhkan_yang_sudah_ditangguhkan_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->post(route('super_admin.users.suspend', $user->ulid), [
            'reason' => 'Alasan yang cukup panjang.',
        ])->assertSessionHasErrors('action');

        $this->assertSame('suspend', session('open_modal'));
    }

    public function test_tiap_saring_pengguna_berdiri_sendiri(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser(['email' => 'saring@sekarya.test']);
        $user->forceFill(['gender' => Gender::Female])->save();

        // Gender saja.
        $this->get(route('super_admin.users.index', ['gender' => 'female']))
            ->assertOk()
            ->assertSee('saring@sekarya.test', false);

        $this->get(route('super_admin.users.index', ['gender' => 'male']))
            ->assertOk()
            ->assertDontSee('saring@sekarya.test', false);

        // Status saja.
        $this->get(route('super_admin.users.index', ['status' => 'banned']))
            ->assertOk()
            ->assertDontSee('saring@sekarya.test', false);
    }

    public function test_saring_kesiapan_pengguna(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $ready = $this->activeUser(['email' => 'siap@sekarya.test']);
        UserWorker::factory()->create(['user_id' => $ready->getKey()]);
        $this->verifyIdentity($ready);
        $plain = $this->activeUser(['email' => 'biasa@sekarya.test']);

        $this->get(route('super_admin.users.index', ['ready' => 'yes']))
            ->assertOk()
            ->assertSee('siap@sekarya.test', false)
            ->assertDontSee('biasa@sekarya.test', false);

        $this->get(route('super_admin.users.index', ['ready' => 'no']))
            ->assertOk()
            ->assertSee('biasa@sekarya.test', false)
            ->assertDontSee('siap@sekarya.test', false);
    }

    public function test_lompat_via_ulid(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();

        $this->get(route('super_admin.users.index', ['ulid' => $user->ulid]))
            ->assertRedirect(route('super_admin.users.show', $user->ulid));

        $this->get(route('super_admin.users.index', ['ulid' => '01KAAAAAAAAAAAAAAAAAAAAAAA']))
            ->assertSessionHasErrors('ulid');
    }
}
