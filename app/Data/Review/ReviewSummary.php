<?php

declare(strict_types=1);

namespace App\Data\Review;

use App\Enums\ReviewerRole;

/** Ringkasan ulasan yang diterima seseorang — angka yang sudah dihitung basis data. */
final readonly class ReviewSummary
{
    /**
     * @param  array<int, int>  $distribution  kunci 5..1 SELALU ada, nol bila tidak ada ulasan
     */
    public function __construct(
        public ?ReviewerRole $role,
        public float $average,
        public int $count,
        public array $distribution,
        /** Persen ulasan bintang lima, dibulatkan ke bilangan bulat. 0 bila belum ada ulasan. */
        public int $fiveStarPercent,
    ) {}
}
