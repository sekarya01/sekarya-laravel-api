<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\VerificationCodeMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Notifications\Notification;

final class VerificationCodeNotification extends Notification
{
    use Queueable;

    /**
     * `$code` publik agar test bisa membacanya dari notifikasi yang di-fake.
     * Nilainya memang payload notifikasi ini, bukan rahasia terhadap
     * pemanggilnya — yang rahasia adalah bentuk tersimpannya di database,
     * dan di sana ia di-hash.
     */
    public function __construct(
        public readonly string $code,
        private readonly int $ttlMinutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        return new VerificationCodeMail(
            code: $this->code,
            name: (string) $notifiable->name,
            ttlMinutes: $this->ttlMinutes,
            email: (string) $notifiable->email,
        );
    }
}
