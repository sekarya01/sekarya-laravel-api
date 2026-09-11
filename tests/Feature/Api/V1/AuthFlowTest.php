<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = [
        'first_name' => 'Budi',
        'last_name' => 'Prasetyo',
        'username' => 'budi.prasetyo',
        'email' => 'budi@sekarya.test',
        'phone' => '+628111222333',
        'password' => 'RahasiaKuat2026',
        'password_confirmation' => 'RahasiaKuat2026',
        'city' => 'Jakarta',
    ];

    // ── register ────────────────────────────────────────────────────────────

    public function test_register_accepts_and_does_not_activate(): void
    {
        Notification::fake();

        $this->postJson(route('v1.auth.register'), self::PAYLOAD)
            ->assertAccepted()
            ->assertJsonPath('data.status', UserStatus::PendingVerification->value)
            ->assertJsonPath('data.email', 'budi@sekarya.test')
            ->assertJsonStructure(['message', 'data' => ['email', 'status', 'code_expires_in_minutes', 'next_step']]);
    }

    /** Mengembalikan token di sini akan membuat verifikasi tanpa arti. */
    public function test_register_returns_no_tokens(): void
    {
        Notification::fake();

        $response = $this->postJson(route('v1.auth.register'), self::PAYLOAD);

        $this->assertStringNotContainsString('access_token', $response->getContent());
        $this->assertStringNotContainsString('long_lived_token', $response->getContent());
    }

    public function test_register_validates_required_fields(): void
    {
        $this->postJson(route('v1.auth.register'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'email', 'password']);
    }

    public function test_register_requires_password_confirmation(): void
    {
        Notification::fake();

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'password_confirmation' => 'beda',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_a_leaked_password(): void
    {
        Notification::fake();

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);
    }

    public function test_register_rejects_a_malformed_phone(): void
    {
        Notification::fake();

        $this->postJson(route('v1.auth.register'), [...self::PAYLOAD, 'phone' => 'bukan-nomor'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_register_rejects_duplicate_email_and_phone(): void
    {
        Notification::fake();
        $this->postJson(route('v1.auth.register'), self::PAYLOAD)->assertAccepted();

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'phone' => '+628999999999',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'email' => 'lain@sekarya.test',
        ])->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    /** Duplikat ditolak dengan pesan spesifik per field. */
    public function test_register_duplicate_errors_name_the_field(): void
    {
        Notification::fake();
        $this->postJson(route('v1.auth.register'), self::PAYLOAD)->assertAccepted();

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'phone' => '+628999999999',
            'username' => 'orang.lain',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Email sudah terdaftar. Masuk atau pakai email lain.');

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'email' => 'lain@sekarya.test',
            'phone' => '+628999999999',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['username'])
            ->assertJsonPath('errors.username.0', 'Username sudah dipakai. Pilih username lain.');

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'email' => 'lain2@sekarya.test',
            'username' => 'orang.lain2',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['phone'])
            ->assertJsonPath('errors.phone.0', 'Nomor HP sudah terdaftar. Masuk atau pakai nomor lain.');
    }

    public function test_register_normalises_the_email_to_lowercase(): void
    {
        Notification::fake();

        $this->postJson(route('v1.auth.register'), [...self::PAYLOAD, 'email' => 'BUDI@Sekarya.Test'])
            ->assertAccepted()
            ->assertJsonPath('data.email', 'budi@sekarya.test');
    }

    public function test_register_accepts_a_missing_phone_last_name_and_username(): void
    {
        Notification::fake();

        $payload = self::PAYLOAD;
        unset($payload['phone'], $payload['last_name'], $payload['username']);

        $this->postJson(route('v1.auth.register'), $payload)->assertAccepted();

        $user = User::query()->where('email', 'budi@sekarya.test')->sole();

        $this->assertSame('Budi', $user->first_name);
        $this->assertNull($user->last_name);
        $this->assertNull($user->username);
        $this->assertNull($user->phone);
        // Tampilan warisan tetap terisi dari nama depan saja.
        $this->assertSame('Budi', $user->name);
    }

    public function test_register_syncs_the_display_name(): void
    {
        Notification::fake();

        $this->postJson(route('v1.auth.register'), self::PAYLOAD)->assertAccepted();

        $user = User::query()->where('email', 'budi@sekarya.test')->sole();

        $this->assertSame('Budi Prasetyo', $user->name);
        $this->assertSame('budi.prasetyo', $user->username);
    }

    public function test_register_rejects_a_duplicate_or_malformed_username(): void
    {
        Notification::fake();
        $this->postJson(route('v1.auth.register'), self::PAYLOAD)->assertAccepted();

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'email' => 'lain@sekarya.test',
            'phone' => '+628999999999',
        ])->assertUnprocessable()->assertJsonValidationErrors(['username']);

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'email' => 'lain2@sekarya.test',
            'phone' => '+628999999998',
            'username' => 'tidak boleh ada spasi!',
        ])->assertUnprocessable()->assertJsonValidationErrors(['username']);
    }

    /** Klien lama yang masih mengirim `name` tidak boleh putus. */
    public function test_register_still_accepts_the_legacy_name_field(): void
    {
        Notification::fake();

        $payload = self::PAYLOAD;
        unset($payload['first_name'], $payload['last_name']);
        $payload['name'] = 'Budi Prasetyo';

        $this->postJson(route('v1.auth.register'), $payload)->assertAccepted();

        $user = User::query()->where('email', 'budi@sekarya.test')->sole();

        $this->assertSame('Budi', $user->first_name);
        $this->assertSame('Prasetyo', $user->last_name);
        $this->assertSame('Budi Prasetyo', $user->name);
    }

    // ── verify ──────────────────────────────────────────────────────────────

    private function registerAndCode(): string
    {
        $this->postJson(route('v1.auth.register'), self::PAYLOAD)->assertAccepted();

        $code = null;
        Notification::assertSentTo(
            User::query()->where('email', 'budi@sekarya.test')->firstOrFail(),
            function (VerificationCodeNotification $notification) use (&$code): bool {
                $code = $notification->code;

                return true;
            },
        );

        return (string) $code;
    }

    public function test_verify_activates_and_returns_a_token_pair(): void
    {
        Notification::fake();
        $code = $this->registerAndCode();

        $this->postJson(route('v1.auth.verify-email'), ['email' => 'budi@sekarya.test', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.status', UserStatus::Active->value)
            ->assertJsonStructure([
                'data' => [
                    'token_type', 'access_token', 'long_lived_token',
                    'access_expires_at', 'long_lived_expires_at', 'access_expires_in_seconds',
                    'user' => ['id', 'name', 'phone', 'as_worker', 'as_poster', 'domicile'],
                ],
            ]);
    }

    public function test_the_access_token_expiry_is_eight_hours(): void
    {
        Notification::fake();
        $code = $this->registerAndCode();

        $seconds = $this->postJson(route('v1.auth.verify-email'), [
            'email' => 'budi@sekarya.test', 'code' => $code,
        ])->json('data.access_expires_in_seconds');

        $this->assertGreaterThan(8 * 3600 - 60, $seconds);
        $this->assertLessThanOrEqual(8 * 3600, $seconds);
    }

    public function test_verify_accepts_a_code_with_separators(): void
    {
        Notification::fake();
        $code = $this->registerAndCode();
        $spaced = substr($code, 0, 3).' '.substr($code, 3);

        $this->postJson(route('v1.auth.verify-email'), ['email' => 'budi@sekarya.test', 'code' => $spaced])
            ->assertOk();
    }

    public function test_verify_reports_remaining_attempts(): void
    {
        Notification::fake();
        $this->registerAndCode();

        $this->postJson(route('v1.auth.verify-email'), ['email' => 'budi@sekarya.test', 'code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_verification_code')
            ->assertJsonPath('context.attempts_left', 4);
    }

    public function test_verify_validates_input(): void
    {
        $this->postJson(route('v1.auth.verify-email'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'code']);
    }

    public function test_verify_hides_whether_the_email_exists(): void
    {
        $this->postJson(route('v1.auth.verify-email'), ['email' => 'hantu@sekarya.test', 'code' => '123456'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');
    }

    // ── resend ──────────────────────────────────────────────────────────────

    public function test_resend_always_answers_the_same(): void
    {
        Notification::fake();

        $unknown = $this->postJson(route('v1.auth.resend-code'), ['email' => 'hantu@sekarya.test'])
            ->assertAccepted();

        $this->activeUser(['email' => 'aktif@sekarya.test']);
        $active = $this->postJson(route('v1.auth.resend-code'), ['email' => 'aktif@sekarya.test'])
            ->assertAccepted();

        $this->assertSame($unknown->json('message'), $active->json('message'));
    }

    public function test_resend_enforces_the_cooldown(): void
    {
        Notification::fake();
        $this->registerAndCode();

        $this->postJson(route('v1.auth.resend-code'), ['email' => 'budi@sekarya.test'])
            ->assertStatus(429)
            ->assertJsonPath('code', 'resend_too_soon')
            ->assertJsonStructure(['context' => ['retry_after_seconds']]);
    }

    public function test_resend_validates_the_email(): void
    {
        $this->postJson(route('v1.auth.resend-code'), ['email' => 'bukan-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    // ── login ───────────────────────────────────────────────────────────────

    private function verifiedUser(): User
    {
        return $this->activeUser([
            'email' => 'budi@sekarya.test',
            'username' => 'budi.prasetyo',
            'phone' => '+628111222333',
            'password' => Hash::make('RahasiaKuat2026'),
        ]);
    }

    public function test_login_with_email(): void
    {
        $this->verifiedUser();

        $this->postJson(route('v1.auth.login'), ['email' => 'budi@sekarya.test', 'password' => 'RahasiaKuat2026'])
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token', 'long_lived_token']]);
    }

    public function test_login_with_username(): void
    {
        $this->verifiedUser();

        $this->postJson(route('v1.auth.login'), ['username' => 'budi.prasetyo', 'password' => 'RahasiaKuat2026'])
            ->assertOk();
    }

    public function test_login_requires_email_or_username(): void
    {
        $this->postJson(route('v1.auth.login'), ['password' => 'x'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'username']);
    }

    public function test_login_rejects_a_wrong_password(): void
    {
        $this->verifiedUser();

        $this->postJson(route('v1.auth.login'), ['email' => 'budi@sekarya.test', 'password' => 'salah'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'invalid_credentials');
    }

    /** Balasan email tak terdaftar dan kata sandi salah harus identik. */
    public function test_login_does_not_reveal_whether_the_email_exists(): void
    {
        $this->verifiedUser();

        $wrongPassword = $this->postJson(route('v1.auth.login'), [
            'email' => 'budi@sekarya.test', 'password' => 'salah',
        ]);
        $unknownEmail = $this->postJson(route('v1.auth.login'), [
            'email' => 'hantu@sekarya.test', 'password' => 'apa saja',
        ]);

        $this->assertSame($wrongPassword->status(), $unknownEmail->status());
        $this->assertSame($wrongPassword->json('message'), $unknownEmail->json('message'));
        $this->assertSame($wrongPassword->json('code'), $unknownEmail->json('code'));
    }

    public function test_login_blocks_unverified_accounts(): void
    {
        $user = $this->verifiedUser();
        $user->forceFill(['status' => UserStatus::PendingVerification, 'email_verified_at' => null])->save();

        $this->postJson(route('v1.auth.login'), ['email' => 'budi@sekarya.test', 'password' => 'RahasiaKuat2026'])
            ->assertForbidden()
            ->assertJsonPath('code', 'email_not_verified');
    }

    public function test_login_blocks_suspended_accounts(): void
    {
        $user = $this->verifiedUser();
        $user->forceFill(['status' => UserStatus::Suspended])->save();

        $this->postJson(route('v1.auth.login'), ['email' => 'budi@sekarya.test', 'password' => 'RahasiaKuat2026'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_not_active')
            ->assertJsonPath('context.status', 'suspended');
    }

    // ── refresh & logout ────────────────────────────────────────────────────

    public function test_refresh_needs_the_long_lived_token(): void
    {
        $user = $this->activeUser();

        $this->asUserWithLongLived($user)
            ->postJson(route('v1.auth.refresh'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['token_type', 'access_token', 'access_expires_at', 'access_expires_in_seconds']]);
    }

    public function test_refresh_does_not_return_the_long_lived_token_again(): void
    {
        $user = $this->activeUser();

        $response = $this->asUserWithLongLived($user)->postJson(route('v1.auth.refresh'));

        $this->assertStringNotContainsString('long_lived_token', $response->getContent());
    }

    public function test_refresh_without_any_token_is_unauthorized(): void
    {
        $this->postJson(route('v1.auth.refresh'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    public function test_logout_revokes_every_token(): void
    {
        $user = $this->activeUser();
        $pair = app(TokenIssuer::class)->issuePair($user);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$pair['access']->plainTextToken,
            'Accept' => 'application/json',
        ])->postJson(route('v1.auth.logout'))->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_logout_accepts_the_long_lived_token_too(): void
    {
        $user = $this->activeUser();

        $this->asUserWithLongLived($user)->postJson(route('v1.auth.logout'))->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
