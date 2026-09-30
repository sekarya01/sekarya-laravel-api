<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Siklus hidup room chat. Satu arah, tidak ada jalan balik:
 *
 *  - `active`      → baca & kirim.
 *  - `expired`     → task berakhir (completed/cancelled/refunded/expired);
 *                    riwayat tetap terbaca, kirim ditolak `chat_room_expired`.
 *  - `deactivated` → room + SELURUH pesan & lampiran dihapus permanen
 *                    (pengelola, atau otomatis N hari setelah expired).
 */
enum ChatRoomStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Deactivated = 'deactivated';

    public function acceptsMessages(): bool
    {
        return $this === self::Active;
    }
}
