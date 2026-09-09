<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kode verifikasi Sekarya: '.$this->code)
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Masukkan kode berikut untuk mengaktifkan akun Sekarya Anda:')
            ->line('**'.$this->code.'**')
            ->line("Kode berlaku {$this->ttlMinutes} menit.")
            ->line('Jika Anda tidak merasa mendaftar, abaikan email ini — akun tidak akan aktif tanpa kode.');
    }
}
