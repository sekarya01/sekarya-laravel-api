<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Sudah ada permintaan pembatalan `pending` untuk task ini.
 *
 * Satu task satu antrean: peminta harus menarik dulu yang lama sebelum
 * meminta lagi, dan pekerja hanya punya satu popup untuk dijawab.
 */
final class CancelRequestPendingException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Sudah ada permintaan pembatalan yang menunggu jawaban pekerja.');
    }

    public function errorCode(): string
    {
        return 'cancel_request_pending';
    }
}
