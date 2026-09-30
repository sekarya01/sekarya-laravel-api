<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Batas ukuran/durasi per jenis lampiran (config `sekarya.chat.limits`). */
final class ChatAttachmentTooLargeException extends DomainException
{
    /** @param array<string, int> $limits */
    private function __construct(string $message, private readonly string $kind, private readonly array $limits)
    {
        parent::__construct($message);
    }

    /** @param array<string, int> $limits */
    public static function for(string $kind, array $limits): self
    {
        return new self('Lampiran melebihi batas ukuran atau durasi.', $kind, $limits);
    }

    public function errorCode(): string
    {
        return 'chat_attachment_too_large';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['kind' => $this->kind, ...$this->limits];
    }
}
