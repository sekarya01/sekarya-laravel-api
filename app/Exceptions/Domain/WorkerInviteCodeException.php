<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Kegagalan redeem kode mitra — pesannya untuk manusia, `errorCode()` untuk mesin.
 *
 * Klien mobile memetakan kode ini ke kalimatnya sendiri; `message` boleh berubah.
 */
final class WorkerInviteCodeException extends DomainException
{
    private function __construct(private readonly string $machineCode, string $message)
    {
        parent::__construct($message);
    }

    public static function invalid(): self
    {
        return new self('worker_invite_invalid', 'Kode undangan tidak ditemukan. Periksa kembali kode Anda.');
    }

    public static function inactive(): self
    {
        return new self('worker_invite_inactive', 'Kode undangan ini sudah dinonaktifkan.');
    }

    public static function expired(): self
    {
        return new self('worker_invite_expired', 'Kode undangan ini sudah kedaluwarsa.');
    }

    public static function exhausted(): self
    {
        return new self('worker_invite_exhausted', 'Kode undangan ini sudah mencapai batas pemakaian.');
    }

    public static function alreadyRedeemed(): self
    {
        return new self('worker_invite_already_redeemed', 'Anda sudah memakai kode undangan ini.');
    }

    public static function alreadyWorker(): self
    {
        return new self('worker_already_registered', 'Akun ini sudah terdaftar sebagai mitra pekerja.');
    }

    public function errorCode(): string
    {
        return $this->machineCode;
    }

    public function httpStatus(): int
    {
        return $this->machineCode === 'worker_invite_invalid' ? 404 : 422;
    }
}
