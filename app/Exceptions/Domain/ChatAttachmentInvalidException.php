<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Lampiran tidak bisa dipakai: bukan milik pengirim, beda room, sudah
 * terpakai pesan lain, atau jenisnya tidak cocok dengan jenis pesan.
 */
final class ChatAttachmentInvalidException extends DomainException
{
    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function because(string $reason): self
    {
        return new self('Lampiran tidak valid untuk pesan ini.', $reason);
    }

    public function errorCode(): string
    {
        return 'chat_attachment_invalid';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['reason' => $this->reason];
    }
}
