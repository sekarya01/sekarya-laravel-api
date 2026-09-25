<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * "Konfirmasi Selesai" ditekan padahal belum ada hasil yang diserahkan.
 *
 * Bukan no-op senyap: menekan tombol yang tidak melakukan apa-apa dan
 * membalas sukses membuat pemberi kerja mengira dananya sudah dilepas.
 */
final class NoSubmittedActivitiesException extends DomainException
{
    public static function make(): self
    {
        return new self('Belum ada hasil pekerjaan yang diserahkan untuk disetujui.');
    }

    public function errorCode(): string
    {
        return 'no_submitted_activities';
    }
}
