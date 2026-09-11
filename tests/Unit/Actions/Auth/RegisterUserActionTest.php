<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Data\Auth\RegisterData;
use App\Enums\UserActiveMode;
use App\Enums\UserStatus;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class RegisterUserActionTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $override = []): RegisterData
    {
        return new RegisterData(
            firstName: $override['first_name'] ?? 'Budi',
            lastName: $override['last_name'] ?? 'Prasetyo',
            username: $override['username'] ?? 'budi.prasetyo',
            email: $override['email'] ?? 'budi@sekarya.test',
            // `??` menelan null eksplisit — untuk phone, null adalah nilai uji.
            phone: array_key_exists('phone', $override) ? $override['phone'] : '+628111222333',
            password: $override['password'] ?? 'RahasiaKuat2026',
            city: $override['city'] ?? 'Jakarta',
            province: $override['province'] ?? 'DKI Jakarta',
        );
    }

    public function test_it_creates_the_account_pending_verification(): void
    {
        Notification::fake();

        $user = app(RegisterUserAction::class)->handle($this->data(), '127.0.0.1');

        $this->assertSame(UserStatus::PendingVerification, $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(UserActiveMode::Hiring, $user->active_mode);
        $this->assertSame('Jakarta', $user->city);
    }

    /**
     * Regresi untuk bug nyata: `status` sengaja tidak fillable, jadi
     * menyertakannya di array atribut akan dibuang tanpa suara dan default
     * kolom yang berlaku. Yang diperiksa di sini adalah nilai yang BENAR-BENAR
     * tersimpan di database, bukan yang ada di objek model.
     */
    public function test_status_is_persisted_not_silently_dropped(): void
    {
        Notification::fake();

        $user = app(RegisterUserAction::class)->handle($this->data());

        $stored = \DB::table('users')->where('id', $user->getKey())->value('status');

        $this->assertSame(UserStatus::PendingVerification->value, $stored);
        $this->assertNotSame(UserStatus::Active->value, $stored);
    }

    public function test_it_syncs_the_display_name_from_first_and_last(): void
    {
        Notification::fake();

        $user = app(RegisterUserAction::class)->handle($this->data());

        $this->assertSame('Budi', $user->first_name);
        $this->assertSame('Prasetyo', $user->last_name);
        $this->assertSame('budi.prasetyo', $user->username);
        $this->assertSame('Budi Prasetyo', $user->name);
    }

    public function test_phone_is_optional(): void
    {
        Notification::fake();

        $user = app(RegisterUserAction::class)->handle($this->data(['phone' => null]));

        $this->assertNull($user->phone);
    }

    public function test_it_hashes_the_password(): void
    {
        Notification::fake();

        $user = app(RegisterUserAction::class)->handle($this->data());

        $this->assertNotSame('RahasiaKuat2026', $user->password);
        $this->assertTrue(Hash::check('RahasiaKuat2026', $user->password));
    }

    public function test_it_issues_a_verification_code_and_sends_it(): void
    {
        Notification::fake();

        $user = app(RegisterUserAction::class)->handle($this->data(), '10.0.0.1');

        $code = $this->latestVerificationCodeFor($user);
        $this->assertSame(0, $code->attempts);
        $this->assertNull($code->consumed_at);
        $this->assertTrue($code->expires_at->isFuture());
        $this->assertSame('10.0.0.1', $code->request_ip);

        Notification::assertSentTo($user, VerificationCodeNotification::class);
    }

    /** Kode disimpan sebagai hash, bukan angkanya. */
    public function test_the_code_is_stored_hashed(): void
    {
        Notification::fake();

        $user = app(RegisterUserAction::class)->handle($this->data());
        $hash = $this->latestVerificationCodeFor($user)->code_hash;

        $this->assertNotEmpty($hash);
        $this->assertDoesNotMatchRegularExpression('/^\d{6}$/', $hash);
        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function test_it_generates_a_ulid(): void
    {
        Notification::fake();

        $user = app(RegisterUserAction::class)->handle($this->data());

        $this->assertSame(26, strlen((string) $user->ulid));
    }

    public function test_the_first_code_ignores_the_resend_cooldown(): void
    {
        Notification::fake();

        // Tidak boleh melempar ResendTooSoonException walaupun belum ada jeda.
        $user = app(RegisterUserAction::class)->handle($this->data());

        $this->assertSame(1, EmailVerificationCode::query()
            ->where('user_id', $user->getKey())->count());
    }

    public function test_it_rolls_back_everything_when_notification_fails(): void
    {
        // Notifikasi tidak di-fake dan mailer 'array' tetap berhasil, jadi
        // kegagalan disimulasikan dengan email yang menabrak unique.
        Notification::fake();
        app(RegisterUserAction::class)->handle($this->data());

        $before = User::query()->count();

        try {
            app(RegisterUserAction::class)->handle($this->data());
            $this->fail('email duplikat seharusnya gagal');
        } catch (\Throwable) {
            // diharapkan
        }

        $this->assertSame($before, User::query()->count());
    }
}
