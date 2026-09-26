<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Akun tidak bisa dihapus selama masih ada tanggungan (G3).
 *
 * Soft-delete + anonimisasi TIDAK boleh menyentuh akun yang masih punya
 * pekerjaan berjalan, pekerjaan yang ia kerjakan, atau permintaan dompet yang
 * menggantung — dana dan orang lain bergantung pada baris-baris itu. Yang
 * hanya butuh dibersihkan (task `draft`/`open`, penawaran menggantung)
 * dibereskan sendiri oleh Action-nya; yang di sini adalah sisa yang harus
 * diselesaikan pengguna dulu.
 */
final class AccountHasActiveObligationsException extends DomainException
{
    /** @param list<string> $reasons */
    private function __construct(string $message, private readonly array $reasons)
    {
        parent::__construct($message);
    }

    /** @param list<string> $reasons */
    public static function because(array $reasons): self
    {
        return new self(
            'Akun masih punya tanggungan yang harus diselesaikan lebih dulu.',
            $reasons,
        );
    }

    public function errorCode(): string
    {
        return 'account_has_active_obligations';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['reasons' => $this->reasons];
    }
}
