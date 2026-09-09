<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\LogoutAction;
use App\Actions\Auth\RefreshAccessTokenAction;
use App\Actions\Auth\ResendVerificationCodeAction;
use App\Data\Auth\ResendCodeData;
use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Exceptions\Domain\AccountNotActiveException;
use App\Exceptions\Domain\ResendTooSoonException;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class RefreshLogoutResendActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_revokes_the_old_access_token(): void
    {
        $user = $this->activeUser();
        $pair = app(TokenIssuer::class)->issuePair($user);
        $oldId = $pair['access']->accessToken->getKey();

        app(RefreshAccessTokenAction::class)->handle($user);

        $this->assertNull($user->tokens()->find($oldId), 'access token lama harus dicabut');
    }

    public function test_refresh_keeps_the_long_lived_token(): void
    {
        $user = $this->activeUser();
        $pair = app(TokenIssuer::class)->issuePair($user);
        $longId = $pair['long_lived']->accessToken->getKey();

        app(RefreshAccessTokenAction::class)->handle($user);

        $this->assertNotNull($user->tokens()->find($longId), 'long_lived tidak boleh berubah');
    }

    public function test_the_new_access_token_has_access_ability_only(): void
    {
        $user = $this->activeUser();
        app(TokenIssuer::class)->issuePair($user);

        $new = app(RefreshAccessTokenAction::class)->handle($user);

        $this->assertSame([TokenAbility::Access->value], $new->accessToken->abilities);
    }

    /** Akun yang di-suspend setelah login tidak boleh memperpanjang akses. */
    public function test_suspended_account_cannot_refresh(): void
    {
        $user = $this->activeUser();
        app(TokenIssuer::class)->issuePair($user);
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->expectException(AccountNotActiveException::class);

        app(RefreshAccessTokenAction::class)->handle($user->refresh());
    }

    public function test_logout_revokes_every_token_including_long_lived(): void
    {
        $user = $this->activeUser();
        app(TokenIssuer::class)->issuePair($user);
        $this->assertSame(2, $user->tokens()->count());

        app(LogoutAction::class)->handle($user);

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_resend_replaces_the_previous_code(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'budi@sekarya.test']);
        $user->forceFill(['status' => UserStatus::PendingVerification, 'email_verified_at' => null])->save();

        $first = $this->issueVerificationCode($user);
        $firstRow = $this->latestVerificationCodeFor($user);

        // Lewati cooldown.
        $firstRow->forceFill(['last_sent_at' => now()->subMinutes(5)])->save();

        app(ResendVerificationCodeAction::class)->handle(new ResendCodeData('budi@sekarya.test'));

        $this->assertNotNull($firstRow->refresh()->consumed_at, 'kode lama harus dibatalkan');
        $this->assertSame(2, EmailVerificationCode::query()->where('user_id', $user->getKey())->count());
        $this->assertNotEmpty($first);
    }

    public function test_resend_respects_the_cooldown(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'budi@sekarya.test']);
        $user->forceFill(['status' => UserStatus::PendingVerification, 'email_verified_at' => null])->save();
        $this->issueVerificationCode($user);

        try {
            app(ResendVerificationCodeAction::class)->handle(new ResendCodeData('budi@sekarya.test'));
            $this->fail('cooldown seharusnya menolak');
        } catch (ResendTooSoonException $e) {
            $this->assertSame('resend_too_soon', $e->errorCode());
            $this->assertSame(429, $e->httpStatus());
            $this->assertGreaterThan(0, $e->context()['retry_after_seconds']);
        }
    }

    /** Endpoint kirim ulang tidak boleh jadi alat pengecek keberadaan akun. */
    public function test_resend_is_silent_for_unknown_email(): void
    {
        Notification::fake();

        app(ResendVerificationCodeAction::class)->handle(new ResendCodeData('hantu@sekarya.test'));

        $this->assertSame(0, EmailVerificationCode::query()->count());
        Notification::assertNothingSent();
    }

    public function test_resend_is_silent_for_already_active_account(): void
    {
        Notification::fake();
        $this->activeUser(['email' => 'aktif@sekarya.test']);

        app(ResendVerificationCodeAction::class)->handle(new ResendCodeData('aktif@sekarya.test'));

        $this->assertSame(0, EmailVerificationCode::query()->count());
        Notification::assertNothingSent();
    }
}
