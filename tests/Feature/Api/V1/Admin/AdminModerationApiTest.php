<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\User;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Moderasi pengguna dan pengelolaan akun pengelola, lewat HTTP. */
final class AdminModerationApiTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->admin = $this->activeAdmin();
        $this->user = $this->activeUser(['email' => 'budi@sekarya.test']);
    }

    // ── Daftar pengguna ─────────────────────────────────────────────────────

    public function test_users_can_be_listed_and_looked_up_by_exact_email(): void
    {
        $this->asAdmin($this->admin)->getJson(route('v1.admin.users.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'phone', 'status', 'active_mode',
                    'email_verified', 'identity_verified', 'city', 'province',
                    'as_worker', 'as_poster', 'cancellations', 'last_active_at', 'created_at']],
            ]);

        $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.users.index', ['email' => 'budi@sekarya.test']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'budi@sekarya.test');

        // Alamat yang tidak ada mengembalikan daftar kosong, BUKAN 422 —
        // kalau 422, daftar pengguna menjadi alat menebak alamat terdaftar.
        $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.users.index', ['email' => 'tidakada@sekarya.test']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_user_list_never_carries_a_password_hash(): void
    {
        $body = $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.users.index'))
            ->assertOk()
            ->content();

        foreach (['password', '$2y$', 'remember_token', 'npwp'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, $needle);
        }
    }

    // ── Moderasi ────────────────────────────────────────────────────────────

    /**
     * Yang benar-benar menghentikan orangnya: tokennya mati SEKARANG.
     *
     * Diuji lewat HTTP karena hanya di sini terlihat: token itu dipegang
     * klien, dan status di basis data tidak menyentuhnya. Tanpa pencabutan,
     * akun yang di-ban tetap bisa menawar sampai delapan jam ke depan.
     */
    public function test_suspending_a_user_kills_the_session_they_already_hold(): void
    {
        $token = app(TokenIssuer::class)->issuePair($this->user)['access']->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->getJson(route('v1.me.show'))->assertOk();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.users.suspend', $this->user), [
                'reason' => 'Melaporkan transfer palsu dua kali berturut-turut.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->getJson(route('v1.me.show'))
            ->assertUnauthorized();
    }

    public function test_a_suspended_user_cannot_log_in_again(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.users.suspend', $this->user), [
                'reason' => 'Melaporkan transfer palsu dua kali berturut-turut.',
            ])->assertOk();

        $this->postJson(route('v1.auth.login'), [
            'email' => 'budi@sekarya.test',
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_not_active');
    }

    public function test_banning_and_reinstating_round_trips(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.users.ban', $this->user), [
                'reason' => 'Identitas palsu, dipakai untuk menipu berkali-kali.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'banned');

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.users.reinstate', $this->user))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');

        $this->postJson(route('v1.auth.login'), [
            'email' => 'budi@sekarya.test',
            'password' => 'password',
        ])->assertOk();
    }

    /** Moderasi tidak boleh menjadi jalan melewati verifikasi email. */
    public function test_reinstating_an_unverified_account_leaves_it_pending(): void
    {
        $unverified = User::factory()->unverified()->create();
        $unverified->forceFill(['status' => UserStatus::Suspended])->save();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.users.reinstate', $unverified))
            ->assertOk()
            ->assertJsonPath('data.status', 'pending_verification')
            ->assertJsonPath('data.email_verified', false);
    }

    public function test_suspending_requires_a_reason(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.users.suspend', $this->user))
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['reason']]);

        $this->assertSame(UserStatus::Active, $this->user->refresh()->status);
    }

    public function test_a_user_cannot_moderate_anyone(): void
    {
        $other = $this->activeUser();

        $this->asUser($this->user)
            ->postJson(route('v1.admin.users.ban', $other), ['reason' => 'saya tidak suka dia'])
            ->assertUnauthorized();

        $this->assertSame(UserStatus::Active, $other->refresh()->status);
    }

    // ── Akun pengelola: hanya super_admin ───────────────────────────────────

    /**
     * Penolakan peran memakai BENTUK KONTRAK, bukan galat bawaan Laravel.
     *
     * Gerbangnya middleware justru karena ini: lewat Policy, penolakan yang
     * sama keluar sebagai `{"message": "This action is unauthorized."}` tanpa
     * kode mesin — sementara Action menolak hal yang sama dengan
     * `admin_access_denied`. Satu kegagalan logis dengan dua bentuk respons
     * memaksa klien bercabang pada `message`.
     */
    public function test_a_plain_admin_cannot_see_or_create_admins(): void
    {
        $this->asAdmin($this->admin)->getJson(route('v1.admin.admins.index'))
            ->assertForbidden()
            ->assertJsonPath('code', 'admin_access_denied')
            ->assertJsonPath('context.reason', 'insufficient_role');

        $this->asAdmin($this->admin)->postJson(route('v1.admin.admins.store'), [
            'name' => 'Orang Baru',
            'email' => 'baru@sekarya.test',
            'password' => 'RahasiaKuatSekali99!',
            'password_confirmation' => 'RahasiaKuatSekali99!',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'admin_access_denied');

        $this->assertDatabaseMissing('admins', ['email' => 'baru@sekarya.test']);
    }

    public function test_the_super_admin_creates_an_admin_that_can_log_in(): void
    {
        $this->asAdmin($this->superAdmin())
            ->postJson(route('v1.admin.admins.store'), [
                'name' => 'Verifikator Dua',
                'email' => 'verif2@sekarya.test',
                'password' => 'RahasiaKuatSekali99!',
                'password_confirmation' => 'RahasiaKuatSekali99!',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.is_super_admin', false);

        // Dan akun itu benar-benar bisa masuk.
        $this->postJson(route('v1.admin.auth.login'), [
            'email' => 'verif2@sekarya.test',
            'password' => 'RahasiaKuatSekali99!',
        ])
            ->assertOk()
            ->assertJsonPath('data.admin.role', 'admin');
    }

    /**
     * Peran TIDAK bisa dikirim lewat payload.
     *
     * Kalau bisa, endpoint ini adalah jalan membuat super_admin kedua — dan
     * yang menahannya cuma aturan validasi, yang berubah setiap kali ada
     * orang menambah field.
     */
    public function test_the_role_cannot_be_smuggled_in_through_the_payload(): void
    {
        $this->asAdmin($this->superAdmin())
            ->postJson(route('v1.admin.admins.store'), [
                'name' => 'Penyusup',
                'email' => 'penyusup@sekarya.test',
                'password' => 'RahasiaKuatSekali99!',
                'password_confirmation' => 'RahasiaKuatSekali99!',
                'role' => 'super_admin',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.role', 'admin');

        $this->assertDatabaseHas('admins', [
            'email' => 'penyusup@sekarya.test',
            'role' => 'admin',
        ]);
    }

    public function test_an_admin_password_must_be_strong(): void
    {
        $this->asAdmin($this->superAdmin())
            ->postJson(route('v1.admin.admins.store'), [
                'name' => 'Lemah',
                'email' => 'lemah@sekarya.test',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['password']]);
    }

    public function test_the_super_admin_deletes_an_admin_and_kills_its_session(): void
    {
        $target = $this->activeAdmin(['email' => 'sementara@sekarya.test']);
        $token = app(TokenIssuer::class)->issuePair($target)['access']->plainTextToken;

        $this->asAdmin($this->superAdmin())
            ->deleteJson(route('v1.admin.admins.destroy', $target))
            ->assertOk()
            ->assertJsonPath('message', 'Akun pengelola dihapus.');

        $this->assertSoftDeleted('admins', ['id' => $target->getKey()]);

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->getJson(route('v1.admin.me.show'))
            ->assertUnauthorized();
    }

    public function test_the_super_admin_cannot_be_deleted_over_http(): void
    {
        $superAdmin = $this->superAdmin();

        $this->asAdmin($superAdmin)
            ->deleteJson(route('v1.admin.admins.destroy', $superAdmin))
            ->assertForbidden()
            ->assertJsonPath('code', 'super_admin_protected');

        $this->assertDatabaseHas('admins', [
            'id' => $superAdmin->getKey(),
            'deleted_at' => null,
        ]);
    }

    public function test_a_plain_admin_cannot_delete_another_admin(): void
    {
        $target = $this->activeAdmin();

        $this->asAdmin($this->admin)
            ->deleteJson(route('v1.admin.admins.destroy', $target))
            ->assertForbidden()
            ->assertJsonPath('code', 'admin_access_denied');

        $this->assertDatabaseHas('admins', ['id' => $target->getKey(), 'deleted_at' => null]);
    }

    public function test_the_admin_list_shows_the_role_of_each_account(): void
    {
        $this->asAdmin($this->superAdmin())
            ->getJson(route('v1.admin.admins.index'))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'email', 'role', 'role_label', 'status',
                    'is_super_admin', 'last_login_at', 'created_at']],
            ]);
    }
}
