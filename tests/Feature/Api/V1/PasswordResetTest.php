<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Reset kata sandi: tautan sekali pakai lewat email.
 *
 * Tautan kedaluwarsa oleh DUA kondisi: waktu (60 menit, standar broker)
 * dan pemakaian (token dihapus saat reset berhasil). Keduanya diuji di sini.
 */
final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(User $user): string
    {
        $token = null;

        Notification::assertSentTo(
            $user,
            function (ResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;

                return true;
            },
        );

        $this->assertNotEmpty($token);

        return (string) $token;
    }

    private function formUrl(string $token, string $email): string
    {
        return route('password.reset', ['token' => $token, 'email' => $email]);
    }

    // ── POST /api/v1/auth/forgot-password ──────────────────────────────────

    public function test_forgot_password_rejects_an_unknown_email(): void
    {
        $this->postJson(route('v1.auth.forgot-password'), ['email' => 'hantu@sekarya.test'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'email_not_registered');

        $this->assertSame(
            0,
            DB::table('password_reset_tokens')->where('email', 'hantu@sekarya.test')->count(),
        );
    }

    public function test_forgot_password_validates_the_email_shape(): void
    {
        $this->postJson(route('v1.auth.forgot-password'), ['email' => 'bukan-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_sends_a_single_use_link(): void
    {
        Notification::fake();

        $user = $this->activeUser(['email' => 'budi@sekarya.test']);

        $this->postJson(route('v1.auth.forgot-password'), ['email' => 'budi@sekarya.test'])
            ->assertAccepted()
            ->assertJsonPath('data.email', 'budi@sekarya.test')
            ->assertJsonPath('data.reset_expires_in_minutes', 60)
            ->assertJsonStructure(['message', 'data' => ['email', 'reset_expires_in_minutes', 'next_step']]);

        $token = $this->userWithToken($user);

        // Yang tersimpan hash-nya, bukan tokennya.
        $row = DB::table('password_reset_tokens')->where('email', 'budi@sekarya.test')->sole();
        $this->assertTrue(Hash::check($token, $row->token));
    }

    public function test_forgot_password_subject_carries_no_token(): void
    {
        Notification::fake();

        $user = $this->activeUser(['email' => 'budi@sekarya.test']);
        $this->postJson(route('v1.auth.forgot-password'), ['email' => 'budi@sekarya.test'])->assertAccepted();

        Notification::assertSentTo($user, function (ResetPasswordNotification $notification) use ($user): bool {
            $mail = $notification->toMail($user);

            return ! str_contains($mail->envelope()->subject, $notification->token);
        });
    }

    // ── Form web ───────────────────────────────────────────────────────────

    public function test_the_form_shows_for_a_valid_token(): void
    {
        Notification::fake();

        $user = $this->activeUser(['email' => 'budi@sekarya.test']);
        $this->postJson(route('v1.auth.forgot-password'), ['email' => 'budi@sekarya.test'])->assertAccepted();
        $token = $this->userWithToken($user);

        $this->get($this->formUrl($token, 'budi@sekarya.test'))
            ->assertOk()
            ->assertSee('Perbarui Password', false);
    }

    public function test_the_form_shows_expired_for_a_garbage_token(): void
    {
        $this->activeUser(['email' => 'budi@sekarya.test']);

        $this->get($this->formUrl('token-sampah', 'budi@sekarya.test'))
            ->assertOk()
            ->assertSee('Tautan kedaluwarsa', false);
    }

    // ── POST /reset-password ───────────────────────────────────────────────

    public function test_reset_updates_the_password_and_burns_the_token(): void
    {
        Notification::fake();

        $user = $this->activeUser(['email' => 'budi@sekarya.test']);
        $this->postJson(route('v1.auth.forgot-password'), ['email' => 'budi@sekarya.test'])->assertAccepted();
        $token = $this->userWithToken($user);

        $this->post(route('password.reset.store'), [
            'email' => 'budi@sekarya.test',
            'token' => $token,
            'password' => 'SandiBaruKuat2026',
            'password_confirmation' => 'SandiBaruKuat2026',
        ])->assertRedirect(route('password.reset.success'));

        // Baris yang bertahan, bukan model di memori.
        $this->assertTrue(Hash::check(
            'SandiBaruKuat2026',
            $user->refresh()->password,
        ));

        // Sekali pakai: tokennya sudah terhapus.
        $this->assertSame(
            0,
            DB::table('password_reset_tokens')->where('email', 'budi@sekarya.test')->count(),
        );

        $this->get(route('password.reset.success'))
            ->assertOk()
            ->assertSee('Kata sandi diperbarui', false);

        // Tautan yang sama dibuka lagi → hangus.
        $this->get($this->formUrl($token, 'budi@sekarya.test'))
            ->assertOk()
            ->assertSee('Tautan kedaluwarsa', false);

        // Login jalan dengan sandi baru.
        $this->postJson(route('v1.auth.login'), [
            'email' => 'budi@sekarya.test', 'password' => 'SandiBaruKuat2026',
        ])->assertOk();
    }

    public function test_reset_revokes_existing_tokens(): void
    {
        Notification::fake();

        $user = $this->activeUser(['email' => 'budi@sekarya.test']);
        $pair = app(TokenIssuer::class)->issuePair($user);
        $this->assertSame(2, $user->tokens()->count());

        $this->postJson(route('v1.auth.forgot-password'), ['email' => 'budi@sekarya.test'])->assertAccepted();
        $token = $this->userWithToken($user);

        $this->post(route('password.reset.store'), [
            'email' => 'budi@sekarya.test',
            'token' => $token,
            'password' => 'SandiBaruKuat2026',
            'password_confirmation' => 'SandiBaruKuat2026',
        ])->assertRedirect(route('password.reset.success'));

        $this->assertSame(0, $user->tokens()->count());

        // Token lama mati di endpoint aplikasi.
        $this->withHeaders([
            'Authorization' => 'Bearer '.$pair['access']->plainTextToken,
            'Accept' => 'application/json',
        ])->getJson(route('v1.me.show'))->assertUnauthorized();
    }

    public function test_reset_rejects_a_mismatched_confirmation(): void
    {
        Notification::fake();

        $user = $this->activeUser(['email' => 'budi@sekarya.test']);
        $this->postJson(route('v1.auth.forgot-password'), ['email' => 'budi@sekarya.test'])->assertAccepted();
        $token = $this->userWithToken($user);

        $this->post(route('password.reset.store'), [
            'email' => 'budi@sekarya.test',
            'token' => $token,
            'password' => 'SandiBaruKuat2026',
            'password_confirmation' => 'beda',
        ])->assertSessionHasErrors('password');

        // Sandi lama masih berlaku.
        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_reset_rejects_a_leaked_password(): void
    {
        Notification::fake();

        $user = $this->activeUser(['email' => 'budi@sekarya.test']);
        $this->postJson(route('v1.auth.forgot-password'), ['email' => 'budi@sekarya.test'])->assertAccepted();
        $token = $this->userWithToken($user);

        $this->post(route('password.reset.store'), [
            'email' => 'budi@sekarya.test',
            'token' => $token,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('password');
    }

    public function test_a_token_older_than_sixty_minutes_is_expired(): void
    {
        $user = $this->activeUser(['email' => 'budi@sekarya.test']);

        $token = Password::createToken($user);

        DB::table('password_reset_tokens')
            ->where('email', 'budi@sekarya.test')
            ->update(['created_at' => now()->subMinutes(61)]);

        $this->get($this->formUrl($token, 'budi@sekarya.test'))
            ->assertOk()
            ->assertSee('Tautan kedaluwarsa', false);

        $this->post(route('password.reset.store'), [
            'email' => 'budi@sekarya.test',
            'token' => $token,
            'password' => 'SandiBaruKuat2026',
            'password_confirmation' => 'SandiBaruKuat2026',
        ])->assertOk()->assertSee('Tautan kedaluwarsa', false);
    }
}
