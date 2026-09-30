<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Hanya pengirim yang boleh menghapus pesannya. */
final class ChatMessageNotOwnedException extends DomainException
{
    public static function make(): self
    {
        return new self('Hanya pengirim yang bisa menghapus pesan ini.');
    }

    public function errorCode(): string
    {
        return 'chat_message_not_owned';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
