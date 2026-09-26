<?php

declare(strict_types=1);

namespace App\Enums;

enum DisputeResolution: string
{
    /** Hasil diterima: dana dilepas ke pekerja, task `completed`. */
    case Release = 'release';

    /** Hasil ditolak: dana kembali ke pemberi kerja, task `refunded`. */
    case Refund = 'refund';
}
