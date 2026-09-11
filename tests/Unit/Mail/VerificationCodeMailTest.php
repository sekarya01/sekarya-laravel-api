<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use Tests\TestCase;

final class VerificationCodeMailTest extends TestCase
{
    public function test_the_subject_carries_the_code(): void
    {
        $mail = new VerificationCodeMail('123456', 'Budi Prasetyo', 15);

        $this->assertSame('Kode verifikasi Sekarya: 123456', $mail->envelope()->subject);
    }

    public function test_the_rendered_html_embeds_the_logo_and_shows_the_code(): void
    {
        $html = (new VerificationCodeMail('123456', 'Budi Prasetyo', 15))->render();

        // Logo di-embed (CID), bukan URL luar.
        $this->assertStringContainsString('cid:', $html);
        $this->assertStringContainsString('123456', $html);
        $this->assertStringContainsString('Budi Prasetyo', $html);
        $this->assertStringContainsString('15 menit', $html);
    }

    public function test_the_notification_builds_the_mailable(): void
    {
        $user = User::factory()->make(['name' => 'Budi Prasetyo']);

        $mail = (new VerificationCodeNotification('123456', 15))->toMail($user);

        $this->assertInstanceOf(VerificationCodeMail::class, $mail);
        $this->assertSame('123456', $mail->code);
    }
}
