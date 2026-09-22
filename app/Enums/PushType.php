<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis peristiwa yang dikirim lewat push.
 *
 * Nilainya ikut di payload FCM sebagai `data.type`, dan itulah yang dibaca
 * klien untuk memutuskan ke layar mana notifikasi membuka. Karena itu ia
 * adalah KONTRAK: mengganti nilainya berarti klien lama berhenti mengenalinya.
 *
 * Jenis yang belum dikenal klien harus diperlakukan sebagai notifikasi biasa
 * yang tetap boleh dibuka — bukan diabaikan — supaya menambah jenis baru di
 * server tidak membuat notifikasi lama berhenti berfungsi.
 */
enum PushType: string
{
    case BidPlaced = 'bid_placed';
    case BidAccepted = 'bid_accepted';
}
