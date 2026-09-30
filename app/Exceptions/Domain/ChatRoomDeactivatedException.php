<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Room sudah dinonaktifkan: pesan & lampirannya terhapus permanen. 410 supaya
 * klien tahu harus membuang salinan lokalnya, bukan mencoba lagi.
 */
final class ChatRoomDeactivatedException extends DomainException
{
    public static function make(): self
    {
        return new self('Chat ini sudah dinonaktifkan dan isinya dihapus.');
    }

    public function errorCode(): string
    {
        return 'chat_room_deactivated';
    }

    public function httpStatus(): int
    {
        return 410;
    }
}
