<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Admin;

use App\Actions\Admin\Auth\AdminLoginAction;
use App\Actions\Admin\Auth\AdminLogoutAction;
use App\Actions\Admin\Auth\RefreshAdminTokenAction;
use App\Data\Admin\AdminLoginData;
use App\Enums\AdminStatus;
use App\Enums\TokenAbility;
use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->activeAdmin(['email' => 'verif@sekarya.test']);
    }

    private function login(string $password = 'password', ?string $email = null): array
    {
        return app(AdminLoginAction::class)->handle(
            new AdminLoginData($email ?? 'verif@sekarya.test', $password, '10.0.0.9'),
        );
    }

    public function test_it_issues_a_token_pair_with_admin_abilities(): void
    {
        $result = $this->login();

        $this->assertTrue($result['admin']->is($this->admin));

        // Ability inilah pemisah kedua di belakang guard: token pengguna
        // tidak pernah membawa `admin:access`, jadi ia tidak bisa memanggil
        // /admin walaupun guard-nya suatu hari salah tulis.
        $this->assertSame(
            [TokenAbility::AdminAccess->value],
            $result['access']->accessToken->abilities,
        );
        $this->assertSame(
            [TokenAbility::AdminRefresh->value],
            $result['long_lived']->accessToken->abilities,
        );

        $this->assertNotNull($result['access']->accessToken->expires_at);
        $this->assertSame(
            8,
            (int) round(now()->diffInHours($result['access']->accessToken->expires_at, false)),
        );
    }

    public function test_it_records_the_login(): void
    {
        $this->login();

        $fresh = $this->admin->refresh();

        $this->assertNotNull($fresh->last_login_at);
        $this->assertSame('10.0.0.9', $fresh->last_login_ip);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        $this->login('salah');
    }

    /**
     * Alamat yang tidak terdaftar menjawab SAMA seperti sandi yang salah.
     *
     * Kalau berbeda, endpoint login pengelola menjadi alat menemukan alamat
     * pengelola — dan daftar itu tepat yang dibutuhkan penyerang sebelum
     * mencoba menebak sandinya.
     */
    public function test_an_unknown_address_answers_exactly_like_a_wrong_password(): void
    {
        $unknown = null;
        $wrong = null;

        try {
            $this->login('password', 'tidakada@sekarya.test');
        } catch (InvalidCredentialsException $e) {
            $unknown = [$e->getMessage(), $e->errorCode(), $e->httpStatus()];
        }

        try {
            $this->login('salah');
        } catch (InvalidCredentialsException $e) {
            $wrong = [$e->getMessage(), $e->errorCode(), $e->httpStatus()];
        }

        $this->assertNotNull($unknown);
        $this->assertSame($wrong, $unknown);
    }

    public function test_a_suspended_admin_cannot_log_in(): void
    {
        $this->admin->forceFill(['status' => AdminStatus::Suspended])->save();

        try {
            $this->login();
            $this->fail('pengelola yang dinonaktifkan seharusnya ditolak');
        } catch (AdminAccessDeniedException $e) {
            $this->assertSame('admin_access_denied', $e->errorCode());
            $this->assertSame(403, $e->httpStatus());
            $this->assertSame(['reason' => 'suspended'], $e->context());
        }

        $this->assertSame(0, $this->admin->tokens()->count());
    }

    public function test_logging_in_again_replaces_the_previous_session(): void
    {
        $first = $this->login();
        $second = $this->login();

        $this->assertSame(2, $this->admin->tokens()->count());
        $this->assertNull(
            $this->admin->tokens()->whereKey($first['access']->accessToken->getKey())->first(),
        );
        $this->assertNotNull(
            $this->admin->tokens()->whereKey($second['access']->accessToken->getKey())->first(),
        );
    }

    // ── Refresh ─────────────────────────────────────────────────────────────

    public function test_refresh_rotates_the_access_token_and_keeps_the_long_lived_one(): void
    {
        $pair = $this->login();

        $new = app(RefreshAdminTokenAction::class)->handle($this->admin);

        $this->assertSame([TokenAbility::AdminAccess->value], $new->accessToken->abilities);
        // Yang lama mati begitu yang baru diterbitkan.
        $this->assertNull(
            $this->admin->tokens()->whereKey($pair['access']->accessToken->getKey())->first(),
        );
        // Long_lived tidak diganti.
        $this->assertNotNull(
            $this->admin->tokens()->whereKey($pair['long_lived']->accessToken->getKey())->first(),
        );
    }

    /**
     * Pengelola yang dinonaktifkan SETELAH masuk tidak boleh memperpanjang
     * aksesnya sendiri.
     *
     * Long_lived-nya hidup 30 hari; tanpa pemeriksaan ini pencabutan
     * kewenangan baru berlaku sebulan kemudian.
     */
    public function test_a_suspended_admin_cannot_refresh(): void
    {
        $this->login();
        $this->admin->forceFill(['status' => AdminStatus::Suspended])->save();

        $this->expectException(AdminAccessDeniedException::class);

        app(RefreshAdminTokenAction::class)->handle($this->admin->refresh());
    }

    // ── Logout ──────────────────────────────────────────────────────────────

    /** Termasuk long_lived — kalau tidak, sesinya belum benar-benar berakhir. */
    public function test_logout_revokes_every_token(): void
    {
        $this->login();
        $this->assertSame(2, $this->admin->tokens()->count());

        app(AdminLogoutAction::class)->handle($this->admin);

        $this->assertSame(0, $this->admin->tokens()->count());
    }
}
