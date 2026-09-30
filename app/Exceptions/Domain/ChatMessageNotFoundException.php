<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Pesan tidak ada di room ini (atau room-nya bukan milik pemanggil). */
final class ChatMessageNotFoundException extends DomainException
{
    public static function make(): self
    {
        return new self('Pesan tidak ditemukan.');
    }

    public function errorCode(): string
    {
        return 'chat_message_not_found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
