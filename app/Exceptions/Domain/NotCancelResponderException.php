<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Yang menjawab bukan pekerja yang dimintai persetujuan.
 *
 * Daftar penjawab dikunci saat permintaan dibuat. Pekerja yang diterima
 * SESUDAH itu tidak punya suara di permintaan ini — bukan karena suaranya
 * tidak berharga, tapi karena daftar yang berubah di tengah jalan membuat
 * "semua sudah setuju" berhenti berarti apa pun.
 */
final class NotCancelResponderException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Kamu bukan pekerja yang dimintai persetujuan pembatalan ini.');
    }

    public function errorCode(): string
    {
        return 'not_cancel_responder';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
