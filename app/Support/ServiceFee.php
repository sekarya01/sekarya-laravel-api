<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Config\Repository as Config;

/**
 * Biaya layanan (G6) — persen dari upah pekerja yang dipotong saat dana dilepas.
 *
 * Angkanya datang dari `config/sekarya.php` → `fees.service_percent` (env),
 * bukan ditulis di kode. Nol berarti tidak ada potongan, dan itu bawaannya:
 * fitur yang belum diputuskan nominalnya tidak boleh memotong uang orang.
 */
final class ServiceFee
{
    public function __construct(private readonly Config $config) {}

    public function percent(): float
    {
        return max(0.0, (float) $this->config->get('sekarya.fees.service_percent', 0));
    }

    /** Tarif dalam basis poin (500 = 5,00%) — disimpan apa adanya di baris fee. */
    public function percentBp(): int
    {
        return (int) round($this->percent() * 100);
    }

    /** Biaya untuk upah bruto, dibulatkan ke rupiah penuh. */
    public function forGross(int $gross): int
    {
        if ($gross <= 0) {
            return 0;
        }

        return (int) round($gross * $this->percent() / 100);
    }
}
