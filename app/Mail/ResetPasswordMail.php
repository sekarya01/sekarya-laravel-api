<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email tautan reset kata sandi dengan kop logo Sekarya.
 *
 * Subjek SENGAJA tidak memuat token: subjek email ikut tercatat di log
 * (lihat security.md), dan token reset adalah kredensial sekali pakai.
 */
final class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $url,
        public readonly string $name,
        public readonly int $ttlMinutes,
        public readonly string $email,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to: $this->email,
            subject: 'Tautan reset kata sandi Sekarya',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password',
            with: [
                'url' => $this->url,
                'name' => $this->name,
                'ttlMinutes' => $this->ttlMinutes,
            ],
        );
    }
}
