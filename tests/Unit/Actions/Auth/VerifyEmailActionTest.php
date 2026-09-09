<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Auth;

use App\Actions\Auth\VerifyEmailAction;
use App\Data\Auth\VerifyEmailData;
use App\Enums\TokenAbility;
use App\Enums\UserStatus;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Exceptions\Domain\InvalidVerificationCodeException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class VerifyEmailActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->user = User::factory()->create(['email' => 'budi@sekarya.test']);
        $this->user->status = UserStatus::PendingVerification;
        $this->user->email_verified_at = null;
        $this->user->save();

        // Kode aslinya hanya ada sesaat; diambil dari notifikasi yang dikirim.
        $this->code = $this->issueVerificationCode($this->user);
    }

    public function test_correct_code_activates_the_account(): void
    {
        $result = app(VerifyEmailAction::class)
            ->handle(new VerifyEmailData('budi@sekarya.test', $this->code));

        $this->assertSame(UserStatus::Active, $result['user']->status);
        $this->assertNotNull($result['user']->email_verified_at);
    }

    public function test_it_issues_both_tokens_with_distinct_abilities(): void
    {
        $result = app(VerifyEmailAction::class)
            ->handle(new VerifyEmailData('budi@sekarya.test', $this->code));

        $this->assertSame(
            [TokenAbility::Access->value],
            $result['access']->accessToken->abilities,
        );
        $this->assertSame(
            [TokenAbility::Refresh->value],
            $result['long_lived']->accessToken->abilities,
        );
    }

    public function test_the_access_token_expires_in_eight_hours(): void
    {
        $result = app(VerifyEmailAction::class)
            ->handle(new VerifyEmailData('budi@sekarya.test', $this->code));

        $minutes = (int) round(
            $result['access']->accessToken->created_at
                ->diffInMinutes($result['access']->accessToken->expires_at),
        );

        $this->assertSame(8 * 60, $minutes);
    }

    public function test_it_consumes_the_code(): void
    {
        app(VerifyEmailAction::class)->handle(new VerifyEmailData('budi@sekarya.test', $this->code));

        $this->assertNotNull($this->latestVerificationCodeFor($this->user)->consumed_at);
    }

    /**
     * Regresi untuk bug nyata: increment di dalam transaksi yang melempar akan
     * di-rollback, sehingga batas percobaan per kode tidak berfungsi sama
     * sekali. Yang dicek di sini adalah nilai yang BERTAHAN di database.
     */
    public function test_failed_attempts_persist_across_failed_requests(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            try {
                app(VerifyEmailAction::class)
                    ->handle(new VerifyEmailData('budi@sekarya.test', '000000'));
                $this->fail('kode salah seharusnya ditolak');
            } catch (InvalidVerificationCodeException $e) {
                $this->assertSame(
                    5 - $i,
                    $e->context()['attempts_left'],
                    "percobaan ke-{$i} harus menyisakan ".(5 - $i),
                );
            }
        }

        $this->assertSame(3, $this->latestVerificationCodeFor($this->user)->attempts);
    }

    public function test_correct_code_is_rejected_once_attempts_are_exhausted(): void
    {
        for ($i = 0; $i < 5; $i++) {
            try {
                app(VerifyEmailAction::class)
                    ->handle(new VerifyEmailData('budi@sekarya.test', '000000'));
            } catch (InvalidVerificationCodeException) {
                // habiskan percobaan
            }
        }

        $this->expectException(InvalidVerificationCodeException::class);
        $this->expectExceptionMessage('Percobaan kode habis');

        app(VerifyEmailAction::class)->handle(new VerifyEmailData('budi@sekarya.test', $this->code));
    }

    public function test_expired_code_is_rejected(): void
    {
        $record = $this->latestVerificationCodeFor($this->user);
        $record->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->expectException(InvalidVerificationCodeException::class);

        app(VerifyEmailAction::class)->handle(new VerifyEmailData('budi@sekarya.test', $this->code));
    }

    /** Email tak terdaftar dibalas seperti kredensial salah — jangan bocorkan. */
    public function test_unknown_email_looks_like_invalid_credentials(): void
    {
        $this->expectException(InvalidCredentialsException::class);

        app(VerifyEmailAction::class)->handle(new VerifyEmailData('bukan@sekarya.test', '123456'));
    }

    public function test_already_active_account_cannot_verify_again(): void
    {
        app(VerifyEmailAction::class)->handle(new VerifyEmailData('budi@sekarya.test', $this->code));

        $this->expectException(InvalidVerificationCodeException::class);

        app(VerifyEmailAction::class)->handle(new VerifyEmailData('budi@sekarya.test', $this->code));
    }

    public function test_it_revokes_previous_tokens_when_issuing_a_pair(): void
    {
        $this->user->createToken('lama', ['*']);
        $this->assertSame(1, $this->user->tokens()->count());

        app(VerifyEmailAction::class)->handle(new VerifyEmailData('budi@sekarya.test', $this->code));

        // Hanya pasangan baru yang tersisa.
        $this->assertSame(2, $this->user->tokens()->count());
        $this->assertSame(0, $this->user->tokens()->where('name', 'lama')->count());
    }
}
