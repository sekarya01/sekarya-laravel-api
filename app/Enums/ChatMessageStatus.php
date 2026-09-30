<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Status pesan dari sudut PENGIRIM, dihitung dari penanda baca/terima
 * peserta lain — bukan kolom. `pending`/`failed` hanya ada di klien.
 */
enum ChatMessageStatus: string
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
}
