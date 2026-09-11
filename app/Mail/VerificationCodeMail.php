<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email kode verifikasi dengan kop logo Sekarya.
 *
 * Logo di-embed (CID), bukan URL luar: tampil langsung tanpa
 * "klik untuk menampilkan gambar", dan tidak tergantung file
 * publik mana pun di server.
 */
final class VerificationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly int $ttlMinutes,
        public readonly string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->email,
            subject: 'Kode verifikasi Sekarya: '.$this->code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verification-code',
            with: [
                'code' => $this->code,
                'name' => $this->name,
                'ttlMinutes' => $this->ttlMinutes,
            ],
        );
    }
}
