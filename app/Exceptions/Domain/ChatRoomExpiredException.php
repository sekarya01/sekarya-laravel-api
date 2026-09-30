<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Task sudah berakhir: riwayat chat tetap terbaca, tapi tidak menerima pesan. */
final class ChatRoomExpiredException extends DomainException
{
    public static function make(): self
    {
        return new self('Chat sudah ditutup karena tugas telah berakhir.');
    }

    public function errorCode(): string
    {
        return 'chat_room_expired';
    }
}
