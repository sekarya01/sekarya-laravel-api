<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Target pekerja tidak boleh turun di bawah yang sudah diterima.
 *
 * Penerimaan sudah terjadi: orangnya menunggu, dan pada task berbayar tagihannya
 * sudah terbentuk. Menurunkan target di bawah angka itu akan menyisakan pekerja
 * yang diterima tanpa slot — keadaan yang tidak punya arti di sisa alurnya.
 */
final class WorkersNeededBelowHiredException extends DomainException
{
    private function __construct(
        string $message,
        private readonly int $requested,
        private readonly int $hired,
    ) {
        parent::__construct($message);
    }

    public static function make(int $requested, int $hired): self
    {
        return new self(
            "Jumlah pekerja tidak bisa diturunkan ke {$requested}: sudah ada {$hired} pekerja yang diterima.",
            $requested,
            $hired,
        );
    }

    public function errorCode(): string
    {
        return 'workers_needed_below_hired';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['requested' => $this->requested, 'workers_hired' => $this->hired];
    }
}
