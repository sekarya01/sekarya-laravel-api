<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Tidak ada permintaan pembatalan `pending` untuk dijawab/diubah.
 *
 * Satu-satunya jawaban untuk "sudah dijawab atau belum pernah diminta":
 * klien memperlakukannya sebagai keadaan akhir, bukan galat yang perlu
 * ditampilkan (pola yang sama dengan `review_not_allowed` di ulasan).
 */
final class NoPendingCancelRequestException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Tidak ada permintaan pembatalan yang menunggu.');
    }

    public function errorCode(): string
    {
        return 'no_pending_cancel_request';
    }
}
