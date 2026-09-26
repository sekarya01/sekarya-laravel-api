<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\WalletEntryType;
use App\Models\Activity;
use App\Models\PlatformFeeEntry;

/**
 * SATU-SATUNYA tempat upah pekerja dibayarkan (G6).
 *
 * Dua jalur melepas dana ke pekerja — persetujuan pemberi kerja
 * (`ApproveActivityAction`) dan kemenangan sengketa (`ResolveDisputeAction`).
 * Kalau tiap jalur mengkredit sendiri, biaya layanan cepat terlupa di salah
 * satunya, dan yang tertinggal adalah pekerja menerima penuh sementara yang
 * lain dipotong.
 *
 * Dua baris buku besar, bukan satu angka bersih: `earning` BRUTO lalu `fee`
 * DEBIT, sehingga riwayat menunjukkan upah dan potongannya secara terpisah —
 * "kenapa saldo saya tidak sebesar upahnya" terjawab oleh barisnya sendiri.
 */
final class WorkerPayout
{
    public function __construct(
        private readonly WalletLedger $ledger,
        private readonly ServiceFee $fee,
    ) {}

    public function pay(Activity $activity, string $description): void
    {
        $gross = (int) ($activity->agreed_amount ?? 0);

        if ($gross <= 0) {
            return;
        }

        $wallet = $this->ledger->walletFor($activity->worker);

        $this->ledger->credit(
            $wallet,
            WalletEntryType::Earning,
            $gross,
            $activity,
            $description,
        );

        $fee = $this->fee->forGross($gross);

        if ($fee > 0) {
            $this->ledger->debit(
                $wallet,
                WalletEntryType::Fee,
                $fee,
                $activity,
                'Biaya layanan',
            );

            // Sisi lain potongan: pendapatan platform (G6). Uang yang keluar
            // dari dompet pekerja tidak boleh hilang dari neraca.
            PlatformFeeEntry::query()->create([
                'activity_id' => $activity->getKey(),
                'worker_id' => $activity->worker_id,
                'gross_amount' => $gross,
                'fee_amount' => $fee,
                'percent_bp' => $this->fee->percentBp(),
            ]);
        }
    }
}
