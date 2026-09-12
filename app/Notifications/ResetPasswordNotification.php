<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\ResetPasswordMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Notifications\Notification;

final class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * `$token` publik agar test bisa membacanya dari notifikasi yang di-fake,
     * sama seperti kode verifikasi. Token aslinya hanya ada sesaat — di
     * database yang tersimpan cuma hash-nya.
     */
    public function __construct(
        public readonly string $token,
        public readonly string $email,
        private readonly int $ttlMinutes,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): Mailable
    {
        return new ResetPasswordMail(
            url: route('password.reset', ['token' => $this->token, 'email' => $this->email]),
            name: (string) $notifiable->name,
            ttlMinutes: $this->ttlMinutes,
            email: $this->email,
        );
    }
}
