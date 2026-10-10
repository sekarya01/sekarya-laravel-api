<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Sengketa tidak bisa diajukan / ditanggapi dalam keadaan ini.
 * `context.reason` membedakan sebabnya untuk klien.
 */
final class DisputeNotAllowedException extends DomainException
{
    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    /** Hanya hasil yang MENUNGGU penilaian (`submitted`) yang bisa disengketakan. */
    public static function becauseStatus(string $status): self
    {
        return new self(
            "Sengketa hanya bisa diajukan atas hasil yang sudah diserahkan (sekarang: {$status}).",
            'wrong_status',
        );
    }

    public static function notOpen(): self
    {
        return new self('Tidak ada sengketa terbuka untuk pekerjaan ini.', 'not_open');
    }

    public static function alreadyResponded(): self
    {
        return new self('Tanggapan atas sengketa ini sudah dikirim.', 'already_responded');
    }

    public function errorCode(): string
    {
        return 'dispute_not_allowed';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['reason' => $this->reason];
    }
}
