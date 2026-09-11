<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\LoginAction;
use App\Data\Auth\LoginData;
use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Exceptions\Domain\AccountNotActiveException;
use App\Exceptions\Domain\EmailNotVerifiedException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class LoginActionTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $override = []): User
    {
        return $this->activeUser([
            'email' => 'budi@sekarya.test',
            'username' => 'budi.prasetyo',
            'phone' => '+628111222333',
            'password' => Hash::make('RahasiaKuat2026'),
            ...$override,
        ]);
    }

    public function test_login_by_email_returns_a_token_pair(): void
    {
        $this->user();

        $result = app(LoginAction::class)
            ->handle(new LoginData('RahasiaKuat2026', email: 'budi@sekarya.test'));

        $this->assertSame([TokenAbility::Access->value], $result['access']->accessToken->abilities);
        $this->assertSame([TokenAbility::Refresh->value], $result['long_lived']->accessToken->abilities);
    }

    public function test_login_by_username_works_too(): void
    {
        $this->user();

        $result = app(LoginAction::class)
            ->handle(new LoginData('RahasiaKuat2026', username: 'budi.prasetyo'));

        $this->assertSame('budi@sekarya.test', $result['user']->email);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->user();

        $this->expectException(InvalidCredentialsException::class);

        app(LoginAction::class)->handle(new LoginData('salah', email: 'budi@sekarya.test'));
    }

    /** Email tak dikenal dibalas identik dengan kata sandi salah. */
    public function test_unknown_email_gives_the_same_error_as_wrong_password(): void
    {
        $this->user();

        $a = null;
        $b = null;

        try {
            app(LoginAction::class)->handle(new LoginData('salah', email: 'budi@sekarya.test'));
        } catch (InvalidCredentialsException $e) {
            $a = $e->getMessage();
        }

        try {
            app(LoginAction::class)->handle(new LoginData('apa saja', email: 'hantu@sekarya.test'));
        } catch (InvalidCredentialsException $e) {
            $b = $e->getMessage();
        }

        $this->assertNotNull($a);
        $this->assertSame($a, $b, 'pesannya harus identik agar akun tidak bisa dienumerasi');
    }

    public function test_unverified_account_cannot_login(): void
    {
        $user = $this->user();
        $user->forceFill([
            'status' => UserStatus::PendingVerification,
            'email_verified_at' => null,
        ])->save();

        $this->expectException(EmailNotVerifiedException::class);

        app(LoginAction::class)->handle(new LoginData('RahasiaKuat2026', email: 'budi@sekarya.test'));
    }

    public function test_suspended_account_cannot_login(): void
    {
        $user = $this->user();
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        try {
            app(LoginAction::class)->handle(new LoginData('RahasiaKuat2026', email: 'budi@sekarya.test'));
            $this->fail('akun suspended seharusnya ditolak');
        } catch (AccountNotActiveException $e) {
            $this->assertSame('account_not_active', $e->errorCode());
            $this->assertSame(['status' => 'suspended'], $e->context());
            $this->assertSame(403, $e->httpStatus());
        }
    }

    public function test_it_records_last_active_at(): void
    {
        $user = $this->user();
        $this->assertNull($user->last_active_at);

        app(LoginAction::class)->handle(new LoginData('RahasiaKuat2026', email: 'budi@sekarya.test'));

        $this->assertNotNull($user->refresh()->last_active_at);
    }

    public function test_login_revokes_previous_tokens(): void
    {
        $user = $this->user();
        $user->createToken('lama', ['*']);

        app(LoginAction::class)->handle(new LoginData('RahasiaKuat2026', email: 'budi@sekarya.test'));

        $this->assertSame(0, $user->tokens()->where('name', 'lama')->count());
        $this->assertSame(2, $user->tokens()->count());
    }
}
