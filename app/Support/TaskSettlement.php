<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\WalletEntryType;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\WalletEntry;
use Illuminate\Support\Collection;

/**
 * Penyelesaian uang & status task PER MITRA — satu-satunya tempat keduanya
 * diputuskan (persetujuan pemberi kerja, persetujuan otomatis, keputusan
 * sengketa pengelola, pembatalan).
 *
 * Aturannya:
 *
 *  - Upah dibayar SAAT MITRA ITU DISETUJUI, bukan menunggu mitra terakhir.
 *    Kalau menunggu, mitra yang kerjanya baik ikut tertahan oleh sengketa
 *    orang lain. Tagihan (`payments`) tetap `held` sampai semua mitra
 *    diputuskan; rinciannya di buku besar, dirujuk per activity.
 *  - Status task mengikuti AGREGAT activity ([sync]): ada yang masih bekerja
 *    → tetap; semua diputuskan → `completed` (ada yang dibayar) atau
 *    `refunded` (semua dikembalikan); ada sengketa terbuka → `disputed`;
 *    sisanya menunggu penilaian → `submitted`.
 *  - Sisa dana tagihan ([outstanding]) = jumlah tagihan dikurangi yang sudah
 *    dibayar/dikembalikan per activity. Pembatalan & penutupan hanya
 *    mengembalikan SISA itu — mengembalikan seluruh tagihan sesudah ada upah
 *    yang dibayar berarti uang yang sama keluar dua kali.
 *
 * Tidak membuka transaksi sendiri (pola WalletLedger): pemanggil yang
 * membukanya, dan memanggil [lockTask] lebih dulu supaya dua keputusan pada
 * task yang sama tidak sama-sama mengira dirinya bukan yang terakhir.
 */
final class TaskSettlement
{
    public function __construct(
        private readonly WalletLedger $ledger,
        private readonly WorkerPayout $payout,
        private readonly TaskStatusRecorder $recorder,
    ) {}

    /** Kunci baris task — serialisasi semua keputusan pada task ini. */
    public function lockTask(Task $task): Task
    {
        return Task::query()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Setujui satu mitra: `approved` + upahnya dibayar bila dana ditahan.
     * Status asal divalidasi pemanggil (submitted / rejected).
     */
    public function approve(Activity $activity): void
    {
        $payment = $this->lockedPayment($activity->task);
        $funded = $payment?->status === PaymentStatus::Held;

        // Gerbang pembayaran hidup → tidak ada upah tanpa dana ditahan.
        // Selama gerbang mati tagihannya `pending`: pekerjaan tetap ditutup,
        // yang tertunda cuma uangnya (lihat config/sekarya.payments).
        if (! $funded && $payment !== null && (bool) config('sekarya.payments.gate_enabled')) {
            throw InvalidStatusTransitionException::between(
                $payment->status->value,
                PaymentStatus::Released->value,
            );
        }

        $activity->forceFill([
            'status' => ActivityStatus::Approved,
            'approved_at' => now(),
        ])->save();

        $activity->worker->workerProfileOrCreate()->increment('tasks_completed');

        if ($funded) {
            $this->payout->pay($activity, 'Upah task #'.$activity->task->task_number);
        }
    }

    /** Sengketa diputuskan untuk pemberi kerja: upah mitra ini kembali kepadanya. */
    public function refund(Activity $activity): void
    {
        $payment = $this->lockedPayment($activity->task);

        $activity->forceFill(['status' => ActivityStatus::Refunded])->save();

        $amount = (int) ($activity->agreed_amount ?? 0);
        if ($payment?->status === PaymentStatus::Held && $amount > 0) {
            // Ke PEMBAYAR, dirujuk ke activity: idempoten per mitra lewat
            // unique (reference_type, reference_id, type) buku besar.
            $this->ledger->credit(
                $this->ledger->walletFor($payment->payer),
                WalletEntryType::Refund,
                $amount,
                $activity,
                'Pengembalian upah sengketa task #'.$activity->task->task_number,
            );
        }
    }

    /** Samakan status task dengan agregat activity-nya. */
    public function sync(Task $task, ActorType $actorType, ?int $actorId, string $reason): void
    {
        /** @var Collection<int, ActivityStatus> $statuses */
        $statuses = $task->activities()->get(['id', 'status'])->pluck('status');

        if ($statuses->isEmpty() || $statuses->contains(fn (ActivityStatus $s): bool => $s->isWorking())) {
            return;
        }

        if ($statuses->every(fn (ActivityStatus $s): bool => $s->isSettled())) {
            $this->finish($task, $statuses->contains(ActivityStatus::Approved), $actorType, $actorId, $reason);

            return;
        }

        $target = $statuses->contains(ActivityStatus::Rejected) ? TaskStatus::Disputed : TaskStatus::Submitted;
        if ($task->status !== $target && $task->status->canTransitionTo($target)) {
            $this->recorder->move($task, $target, $actorType, $actorId, $reason);
        }
    }

    /**
     * Dana tagihan yang belum dibayar ke mitra maupun dikembalikan per mitra.
     * Dari buku besar (sumber kebenaran), bukan dari status activity.
     */
    public function outstanding(Task $task, Payment $payment): int
    {
        $settled = (int) WalletEntry::query()
            ->where('reference_type', (new Activity)->getTable())
            ->whereIn('reference_id', $task->activities()->select('id'))
            ->whereIn('type', [WalletEntryType::Earning, WalletEntryType::Refund])
            ->sum('amount');

        return max(0, (int) $payment->amount - $settled);
    }

    /**
     * Tutup tagihan yang ditahan: SISA dana kembali ke pembayar, status
     * `released` bila ada upah yang dibayar, `refunded` bila tidak ada.
     * Dipakai saat semua mitra diputuskan dan saat task dibatalkan.
     */
    public function closePayment(Task $task, Payment $payment, string $description): void
    {
        if ($payment->status !== PaymentStatus::Held) {
            return;
        }

        $remainder = $this->outstanding($task, $payment);
        if ($remainder > 0) {
            $this->ledger->credit(
                $this->ledger->walletFor($payment->payer),
                WalletEntryType::Refund,
                $remainder,
                $payment,
                $description,
            );
        }

        $paid = WalletEntry::query()
            ->where('reference_type', (new Activity)->getTable())
            ->whereIn('reference_id', $task->activities()->select('id'))
            ->where('type', WalletEntryType::Earning)
            ->exists();

        $payment->forceFill($paid
            ? ['status' => PaymentStatus::Released, 'released_at' => now()]
            : ['status' => PaymentStatus::Refunded, 'refunded_at' => now()])->save();
    }

    private function finish(Task $task, bool $anyApproved, ActorType $actorType, ?int $actorId, string $reason): void
    {
        $payment = $this->lockedPayment($task);
        $funded = $payment?->status === PaymentStatus::Held;

        if ($funded) {
            // Mitra yang disetujui SEBELUM upah dibayar per mitra (aturan
            // lama: dana dilepas saat mitra terakhir) belum menerima apa pun.
            $task->activities()->with('worker')->where('status', ActivityStatus::Approved)->get()
                ->reject(fn (Activity $a): bool => $this->isPaid($a))
                ->each(fn (Activity $a) => $this->payout->pay($a, 'Upah task #'.$task->task_number));

            $this->closePayment($task, $payment, 'Sisa dana tugas #'.$task->task_number.' dikembalikan');
        }

        $target = $anyApproved ? TaskStatus::Completed : TaskStatus::Refunded;
        if (! $task->status->canTransitionTo($target)) {
            return;
        }

        if ($anyApproved) {
            $task->forceFill(['completed_at' => now()])->save();
        }

        // Jejaknya tidak mengaku-aku: tanpa dana ditahan, tidak ada yang dilepas.
        $this->recorder->move($task, $target, $actorType, $actorId, $funded
            ? $reason.'; semua mitra diputuskan, dana diselesaikan'
            : $reason.'; tugas tanpa dana ditahan, tidak ada yang dilepas');

        // "Layanan Selesai" pemberi kerja (U15): sekali per task yang benar-
        // benar selesai dikerjakan — task yang seluruhnya dikembalikan tidak.
        if ($anyApproved) {
            $task->poster()->firstOrFail()->increment('poster_tasks_completed');
        }
    }

    private function isPaid(Activity $activity): bool
    {
        return WalletEntry::query()
            ->where('reference_type', $activity->getTable())
            ->where('reference_id', $activity->getKey())
            ->where('type', WalletEntryType::Earning)
            ->exists();
    }

    private function lockedPayment(Task $task): ?Payment
    {
        return Payment::query()->where('task_id', $task->getKey())->lockForUpdate()->first();
    }
}
