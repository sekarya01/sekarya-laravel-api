<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Gerbang `/admin`, lewat HTTP.
 *
 * Kelas ini ada terutama untuk SATU pertanyaan: apakah dua populasi pemilik
 * token benar-benar terpisah. Jawabannya bergantung pada satu baris config
 * (`provider` pada guard) yang, kalau hilang, membuat Sanctum meloloskan
 * pemilik token jenis apa pun TANPA galat apa pun. Tidak ada cara mengetahui
 * itu selain mencoba kedua arahnya.
 */
final class AdminAuthApiTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->activeAdmin(['email' => 'verif@sekarya.test']);
    }

    // ── Login ───────────────────────────────────────────────────────────────

    public function test_login_returns_a_token_pair_and_the_admin(): void
    {
        $this->postJson(route('v1.admin.auth.login'), [
            'email' => 'verif@sekarya.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.admin.email', 'verif@sekarya.test')
            ->assertJsonPath('data.admin.role', 'admin')
            ->assertJsonPath('data.admin.is_super_admin', false)
            ->assertJsonStructure([
                'data' => [
                    'token_type', 'access_token', 'long_lived_token',
                    'access_expires_at', 'long_lived_expires_at', 'access_expires_in_seconds',
                    'admin' => ['id', 'name', 'email', 'role', 'role_label', 'status',
                        'is_super_admin', 'last_login_at', 'created_at'],
                ],
            ]);
    }

    /**
     * Batas pengungkapan akun pengelola.
     *
     * `last_login_ip` disimpan untuk penyelidikan, dan itu pekerjaan yang
     * dilakukan di basis data — bukan alasan menaruh alamat jaringan rekan
     * kerja di respons API.
     */
    public function test_the_login_response_never_carries_the_password_or_the_login_ip(): void
    {
        $body = $this->postJson(route('v1.admin.auth.login'), [
            'email' => 'verif@sekarya.test',
            'password' => 'password',
        ])->assertOk()->content();

        foreach (['password', 'last_login_ip', 'remember_token', '$2y$'] as $needle) {
            $this->assertStringNotContainsString($needle, $body, $needle);
        }
    }

    public function test_a_wrong_password_answers_exactly_like_an_unknown_address(): void
    {
        $wrong = $this->postJson(route('v1.admin.auth.login'), [
            'email' => 'verif@sekarya.test',
            'password' => 'salah',
        ])->assertUnauthorized();

        $unknown = $this->postJson(route('v1.admin.auth.login'), [
            'email' => 'tidakada@sekarya.test',
            'password' => 'password',
        ])->assertUnauthorized();

        $this->assertSame($wrong->json(), $unknown->json());
    }

    public function test_a_suspended_admin_cannot_log_in(): void
    {
        $this->admin->forceFill(['status' => AdminStatus::Suspended])->save();

        $this->postJson(route('v1.admin.auth.login'), [
            'email' => 'verif@sekarya.test',
            'password' => 'password',
        ])
            ->assertForbidden()
            ->assertJsonPath('code', 'admin_access_denied')
            ->assertJsonPath('context.reason', 'suspended');
    }

    public function test_admin_login_is_rate_limited(): void
    {
        RateLimiter::clear('');

        $codes = [];

        for ($i = 0; $i < 7; $i++) {
            $codes[] = $this->postJson(route('v1.admin.auth.login'), [
                'email' => 'verif@sekarya.test',
                'password' => 'salah',
            ])->getStatusCode();
        }

        // Lima percobaan per menit; yang keenam sudah ditolak.
        $this->assertContains(429, $codes);
        $this->assertSame(429, $codes[6]);
    }

    // ── PEMISAHAN DUA POPULASI TOKEN ────────────────────────────────────────

    /**
     * Token pengguna TIDAK berlaku di /admin.
     *
     * 401, bukan 403: guard `admin` hanya menerima pemilik token dari tabel
     * `admins`, jadi kegagalannya di autentikasi. Kalau ini pernah menjadi
     * 200, `provider` pada guard di config/auth.php hilang.
     */
    public function test_a_user_token_is_rejected_on_every_admin_endpoint(): void
    {
        $user = $this->activeUser();

        foreach ([
            route('v1.admin.me.show'),
            route('v1.admin.verifications.index'),
            route('v1.admin.payments.index'),
            route('v1.admin.users.index'),
            route('v1.admin.admins.index'),
        ] as $url) {
            $this->asUser($user)->getJson($url)
                ->assertUnauthorized()
                ->assertJsonPath('code', 'unauthenticated');
        }
    }

    /** Dan sebaliknya: token pengelola tidak berlaku di endpoint pengguna. */
    public function test_an_admin_token_is_rejected_on_every_user_endpoint(): void
    {
        foreach ([
            route('v1.me.show'),
            route('v1.tasks.index'),
            route('v1.categories.index'),
            route('v1.activities.mine'),
        ] as $url) {
            $this->asAdmin($this->admin)->getJson($url)
                ->assertUnauthorized()
                ->assertJsonPath('code', 'unauthenticated');
        }
    }

    /**
     * Lapis kedua: ability. Ini yang tetap bekerja kalau lapis pertama
     * (provider guard) suatu hari hilang.
     */
    public function test_the_admin_long_lived_token_cannot_call_admin_endpoints(): void
    {
        $this->asAdminWithLongLived($this->admin)
            ->getJson(route('v1.admin.me.show'))
            ->assertForbidden();

        $this->asAdminWithLongLived($this->admin)
            ->getJson(route('v1.admin.verifications.index'))
            ->assertForbidden();
    }

    public function test_the_admin_access_token_cannot_call_refresh(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.auth.refresh'))
            ->assertForbidden();
    }

    public function test_a_user_long_lived_token_cannot_refresh_an_admin_session(): void
    {
        $this->asUserWithLongLived($this->activeUser())
            ->postJson(route('v1.admin.auth.refresh'))
            ->assertUnauthorized();
    }

    // ── Sesi ────────────────────────────────────────────────────────────────

    public function test_refresh_issues_a_new_access_token_and_kills_the_old_one(): void
    {
        $pair = app(TokenIssuer::class)->issuePair($this->admin);
        $old = $pair['access']->plainTextToken;

        $new = $this->withHeaders([
            'Authorization' => 'Bearer '.$pair['long_lived']->plainTextToken,
            'Accept' => 'application/json',
        ])->postJson(route('v1.admin.auth.refresh'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['token_type', 'access_token',
                'access_expires_at', 'access_expires_in_seconds']])
            ->json('data.access_token');

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$old, 'Accept' => 'application/json'])
            ->getJson(route('v1.admin.me.show'))
            ->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => 'Bearer '.$new, 'Accept' => 'application/json'])
            ->getJson(route('v1.admin.me.show'))
            ->assertOk();
    }

    public function test_logout_revokes_both_tokens(): void
    {
        $pair = app(TokenIssuer::class)->issuePair($this->admin);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$pair['access']->plainTextToken,
            'Accept' => 'application/json',
        ])->postJson(route('v1.admin.auth.logout'))->assertOk();

        $this->assertSame(0, $this->admin->tokens()->count());

        // Long_lived juga mati — kalau tidak, "logout" masih bisa menerbitkan
        // akses baru.
        $this->app['auth']->forgetGuards();
        $this->withHeaders([
            'Authorization' => 'Bearer '.$pair['long_lived']->plainTextToken,
            'Accept' => 'application/json',
        ])->postJson(route('v1.admin.auth.refresh'))->assertUnauthorized();
    }

    /**
     * Pengelola yang dinonaktifkan DI TENGAH SESI langsung tertutup.
     *
     * Status akun tidak tersimpan di dalam token dan tokennya hidup delapan
     * jam, jadi tanpa pemeriksaan per permintaan, pencabutan kewenangan baru
     * berlaku delapan jam kemudian. Ini lapis `admin.active`.
     */
    public function test_suspending_an_admin_closes_a_session_that_is_already_open(): void
    {
        $this->asAdmin($this->admin)->getJson(route('v1.admin.me.show'))->assertOk();

        $this->admin->forceFill(['status' => AdminStatus::Suspended])->save();

        $this->asAdmin($this->admin)->getJson(route('v1.admin.me.show'))
            ->assertForbidden()
            ->assertJsonPath('code', 'admin_access_denied')
            ->assertJsonPath('context.reason', 'suspended');
    }

    /** Tapi ia harus tetap bisa keluar — logout sengaja tanpa `admin.active`. */
    public function test_a_suspended_admin_can_still_log_out(): void
    {
        $token = app(TokenIssuer::class)->issuePair($this->admin)['access']->plainTextToken;
        $this->admin->forceFill(['status' => AdminStatus::Suspended])->save();

        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'])
            ->postJson(route('v1.admin.auth.logout'))
            ->assertOk();

        $this->assertSame(0, $this->admin->tokens()->count());
    }

    public function test_without_a_token_every_admin_endpoint_is_closed(): void
    {
        $this->getJson(route('v1.admin.me.show'))->assertUnauthorized();
        $this->getJson(route('v1.admin.verifications.index'))->assertUnauthorized();
        $this->postJson(route('v1.admin.admins.store'))->assertUnauthorized();
    }

    /** Tidak ada endpoint pendaftaran pengelola, dan itu disengaja. */
    public function test_there_is_no_admin_registration_endpoint(): void
    {
        $names = collect(app('router')->getRoutes())
            ->map(fn ($route) => (string) $route->getName())
            ->filter(fn (string $name) => str_starts_with($name, 'v1.admin.'))
            ->values();

        $this->assertFalse($names->contains('v1.admin.auth.register'));
        $this->assertSame(
            ['v1.admin.auth.login', 'v1.admin.auth.logout', 'v1.admin.auth.refresh'],
            $names->filter(fn (string $n) => str_starts_with($n, 'v1.admin.auth.'))->sort()->values()->all(),
        );
    }

    public function test_me_shows_the_authenticated_admin(): void
    {
        $this->asAdmin($this->admin)->getJson(route('v1.admin.me.show'))
            ->assertOk()
            ->assertJsonPath('data.email', 'verif@sekarya.test')
            ->assertJsonPath('data.status', 'active');
    }
}
