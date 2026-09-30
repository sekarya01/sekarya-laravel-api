<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Pesan yang dibalas tidak ada, beda room, pesan sistem, atau sudah dihapus. */
final class ChatRepliedMessageInvalidException extends DomainException
{
    public static function make(): self
    {
        return new self('Pesan yang dibalas tidak bisa dipakai.');
    }

    public function errorCode(): string
    {
        return 'chat_replied_message_invalid';
    }
}
