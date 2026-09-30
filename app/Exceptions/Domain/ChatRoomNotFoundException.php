<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Room tidak ada ATAU pemanggil bukan pesertanya — satu jawaban untuk keduanya. */
final class ChatRoomNotFoundException extends DomainException
{
    /** 404, bukan 403 — 403 mengonfirmasi bahwa room itu ada. */
    public static function make(): self
    {
        return new self('Chat tidak ditemukan.');
    }

    public function errorCode(): string
    {
        return 'chat_room_not_found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
