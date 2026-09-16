<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Penarikan diminta tanpa rekening yang sudah disetujui pengelola.
 *
 * Rekening bank sengaja BUKAN syarat `ready_to_work` — ia syarat untuk
 * DIBAYAR, bukan untuk boleh bekerja. Di sinilah syarat itu berlaku, dan
 * hanya di sini: pekerja tetap bisa melamar, menang, dan mengumpulkan saldo
 * tanpa pernah menyentuh antrean verifikasi rekening. Yang tidak bisa ia
 * lakukan adalah mengeluarkan uangnya ke rekening yang belum dicocokkan
 * dengan identitasnya.
 */
final class BankAccountNotVerifiedException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Rekening bank belum terverifikasi. Ajukan lewat POST /me/verifications '
            .'dan tunggu persetujuan pengelola sebelum menarik saldo.',
        );
    }

    public function errorCode(): string
    {
        return 'bank_account_not_verified';
    }
}
