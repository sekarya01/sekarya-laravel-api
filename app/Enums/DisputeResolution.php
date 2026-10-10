<?php

declare(strict_types=1);

namespace App\Enums;

enum DisputeResolution: string
{
    /** Hasil diterima: upah MITRA ITU dilepas ke saldonya. */
    case Release = 'release';

    /** Hasil ditolak: upah MITRA ITU kembali ke saldo pemberi kerja. */
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Release => 'Komplain ditolak — upah diteruskan ke mitra',
            self::Refund => 'Komplain diterima — dana dikembalikan ke pemberi kerja',
        };
    }
}
