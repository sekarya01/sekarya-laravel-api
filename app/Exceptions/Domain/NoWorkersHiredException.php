<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Pemberi kerja mencoba memulai pekerjaan tanpa satu pun pelamar diterima.
 *
 * "Mulai dengan yang ada" mengunci jumlah pekerja di angka yang sudah
 * diterima; dengan nol, yang terkunci adalah task tanpa pekerja — bukan
 * keadaan yang bisa dilanjutkan ke mana pun.
 */
final class NoWorkersHiredException extends DomainException
{
    public static function make(): self
    {
        return new self('Belum ada pelamar yang diterima, jadi pekerjaan belum bisa dimulai.');
    }

    public function errorCode(): string
    {
        return 'no_workers_hired';
    }
}
