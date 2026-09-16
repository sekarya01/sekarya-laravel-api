<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Terlalu banyak permintaan saldo yang menunggu keputusan pengelola.
 *
 * Bukan rate limit, dan tidak bisa digantikan olehnya: rate limit membatasi
 * kecepatan, sedangkan yang dijaga di sini adalah JUMLAH yang menggantung.
 * Tiga permintaan per menit selama sehari tetap lolos rate limit dan tetap
 * meninggalkan ribuan baris yang harus dibuka satu per satu oleh pengelola —
 * dan yang menunggu di belakangnya adalah pekerja yang benar-benar menunggu
 * uangnya.
 */
final class TooManyPendingWalletRequestsException extends DomainException
{
    private function __construct(
        private readonly string $subject,
        private readonly int $pending,
        private readonly int $max,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function topups(int $pending, int $max): self
    {
        return new self('topup', $pending, $max, sprintf(
            'Masih ada %d permintaan isi saldo yang menunggu konfirmasi (maksimum %d). '
            .'Tunggu keputusannya atau batalkan salah satunya.',
            $pending,
            $max,
        ));
    }

    public static function withdrawals(int $pending, int $max): self
    {
        return new self('withdrawal', $pending, $max, sprintf(
            'Masih ada %d permintaan penarikan yang sedang diproses (maksimum %d). '
            .'Tunggu keputusannya atau batalkan salah satunya.',
            $pending,
            $max,
        ));
    }

    public function errorCode(): string
    {
        return 'too_many_pending_wallet_requests';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'subject' => $this->subject,
            'pending' => $this->pending,
            'max' => $this->max,
        ];
    }
}
