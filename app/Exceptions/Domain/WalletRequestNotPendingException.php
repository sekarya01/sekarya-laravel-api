<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Permintaan saldo yang sudah diputuskan tidak bisa diputuskan lagi.
 *
 * Terpisah dari `InvalidStatusTransitionException` yang umum karena yang
 * dijaga di sini bukan urutan status, tapi UANG: konfirmasi topup yang
 * terpanggil dua kali akan menambah saldo dua kali, dan pembatalan penarikan
 * yang terpanggil dua kali akan mengembalikan tahanannya dua kali. Indeks
 * unique di `wallet_entries` tetap jadi pengaman terakhir — penjaga ini yang
 * membuat kegagalannya terbaca sebagai aturan bisnis, bukan sebagai #1062.
 */
final class WalletRequestNotPendingException extends DomainException
{
    private function __construct(
        private readonly string $subject,
        private readonly string $status,
    ) {
        parent::__construct(sprintf(
            'Permintaan %s ini sudah berstatus "%s" dan tidak bisa diubah lagi.',
            $subject,
            $status,
        ));
    }

    public static function topup(string $status): self
    {
        return new self('isi saldo', $status);
    }

    public static function withdrawal(string $status): self
    {
        return new self('penarikan saldo', $status);
    }

    public function errorCode(): string
    {
        return 'wallet_request_not_pending';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['subject' => $this->subject, 'status' => $this->status];
    }
}
