<?php

declare(strict_types=1);

namespace App\Data\Wallet;

/**
 * Konfigurasi dompet untuk klien (B1): rekening tujuan + batas nominal.
 *
 * Sumbernya `config/sekarya.php` → `wallet`, yang menarik rekening dari env.
 * Klien tidak boleh menghitung batasnya sendiri — nilai yang melenceng di
 * klien menghasilkan permintaan yang ditolak server tanpa penjelasan.
 */
final readonly class WalletConfig
{
    /**
     * @param  list<TopupAccount>  $topupAccounts
     * @param  array<string, int>  $limits
     */
    public function __construct(
        public array $topupAccounts,
        public array $limits,
    ) {}
}
