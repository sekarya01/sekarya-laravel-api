<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Enums\WalletEntryType;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskFundMovement;

/**
 * Dana tugas yang ditahan dari SALDO pemberi kerja.
 *
 * Aturannya satu: dana yang ditahan untuk sebuah tugas selalu sama dengan
 * KOMITMENNYA — jumlah penawaran yang sudah diterima ditambah slot yang masih
 * kosong dikali harga per orang. Contoh: 5 pekerja × Rp100.000 ditahan
 * Rp500.000 saat tugas dipasang; perekrutan ditutup dengan 3 orang → komitmen
 * turun ke Rp300.000 dan Rp200.000 kembali ke saldo. Yang Rp300.000 itulah yang
 * dibayarkan ke ketiga pekerja saat pekerjaannya disetujui.
 *
 * Karena itu tidak ada "potong sekian" yang ditulis di tempat lain: pemanggil
 * cukup memanggil [sync] setiap kali komitmennya bisa berubah (tugas dipasang,
 * diubah, penawaran diterima, perekrutan ditutup), dan kelas ini yang menagih
 * kekurangannya atau mengembalikan kelebihannya.
 *
 * Tagihan (`payments`) langsung `held`: uangnya sudah pindah dari saldo, jadi
 * tidak ada transfer yang perlu dikonfirmasi pengelola.
 *
 * Saldo yang tidak cukup menggagalkan seluruh transaksi pemanggil
 * (InsufficientBalanceException, `insufficient_balance`) — tugas tidak
 * terpasang, penawaran tidak diterima. Tidak ada tugas yang hidup tanpa dana.
 */
final class TaskEscrow
{
    public function __construct(private readonly WalletLedger $ledger) {}

    /** Harga per orang: batas atas anggaran (sama dengan yang ditawarkan ke pekerja). */
    public function unitPrice(Task $task): int
    {
        return (int) ($task->budget_max ?? $task->budget_min ?? 0);
    }

    /** Penawaran yang sudah diterima + slot kosong × harga per orang. */
    public function commitment(Task $task): int
    {
        $accepted = $task->acceptedBids()->get(['amount']);
        $open = max(0, (int) $task->workers_needed - $accepted->count());

        return (int) $accepted->sum('amount') + $open * $this->unitPrice($task);
    }

    /**
     * Mulai menahan dana tugas ini (tugas dipasang). Tagihan yang belum ada
     * dibuat; tagihan `pending` tanpa uang di dalamnya diambil alih.
     */
    public function start(Task $task, string $reason): Payment
    {
        $payment = $this->lockedPayment($task) ?? Payment::query()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $task->poster_id,
            'status' => PaymentStatus::Held,
            'amount' => 0,
        ]);

        if ($payment->status === PaymentStatus::Pending) {
            $payment->forceFill(['status' => PaymentStatus::Held, 'amount' => 0])->save();
        }

        if ($payment->held_at === null) {
            $payment->forceFill(['held_at' => now()])->save();
        }

        return $this->sync($task, $reason, $payment);
    }

    /**
     * Samakan dana yang ditahan dengan komitmen tugas. Hanya bekerja pada
     * tagihan yang dananya memang ditahan dari saldo (`held`); tagihan lain
     * (tugas lama sebelum aturan ini, atau yang sudah dilepas/dikembalikan)
     * dibiarkan apa adanya.
     */
    public function sync(Task $task, string $reason, ?Payment $payment = null): Payment
    {
        $payment ??= $this->lockedPayment($task);
        if ($payment === null || $payment->status !== PaymentStatus::Held) {
            return $payment ?? new Payment;
        }

        $target = $this->commitment($task);
        $diff = $target - (int) $payment->amount;

        if ($diff !== 0) {
            $movement = new TaskFundMovement;
            $movement->forceFill([
                'task_id' => $task->getKey(),
                'payment_id' => $payment->getKey(),
                'kind' => $diff > 0 ? TaskFundMovement::KIND_HOLD : TaskFundMovement::KIND_RELEASE,
                'amount' => abs($diff),
                'reason' => mb_substr($reason, 0, 120),
                'created_at' => now(),
            ])->save();

            $wallet = $this->ledger->walletFor($task->poster()->firstOrFail());
            if ($diff > 0) {
                $this->ledger->debit(
                    $wallet,
                    WalletEntryType::TaskHold,
                    $diff,
                    $movement,
                    'Dana tugas #'.$task->task_number.' ditahan',
                );
            } else {
                $this->ledger->credit(
                    $wallet,
                    WalletEntryType::TaskRelease,
                    -$diff,
                    $movement,
                    'Sisa dana tugas #'.$task->task_number.' dikembalikan',
                );
            }

            $payment->forceFill(['amount' => $target])->save();
        }

        return $payment;
    }

    /** Apakah tugas ini dibiayai dari saldo (dananya sedang ditahan). */
    public function isHeld(Task $task): bool
    {
        return $task->payment()->value('status') === PaymentStatus::Held->value;
    }

    private function lockedPayment(Task $task): ?Payment
    {
        return Payment::query()
            ->where('task_id', $task->getKey())
            ->lockForUpdate()
            ->first();
    }
}
