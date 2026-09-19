<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\WalletEntryType;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\User;
use App\Support\TaskStatusRecorder;
use App\Support\WalletLedger;
use Illuminate\Database\ConnectionInterface;

/**
 * Pemberi kerja menyetujui hasil → task selesai → DANA DILEPAS KE SALDO.
 *
 * "Dilepas" sekarang punya akibat yang bisa dihitung: setiap pekerja yang
 * diterima mendapat kredit sebesar `activities.agreed_amount` miliknya sendiri
 * di dompetnya. Dari situ ia menariknya ke rekening lewat
 * `POST /me/wallet/withdrawals` — pencairan ke bank tetap manual, tapi
 * pembagiannya tidak lagi menunggu apa pun.
 *
 * Yang dikreditkan HARGA PER ORANG, bukan `tasks.agreed_amount`. Yang terakhir
 * adalah total seluruh pekerja; memakainya berarti setiap orang dari task 30
 * orang menerima seluruh tagihan.
 *
 * Kreditnya ikut di dalam penjaga "pekerja terakhir" yang sama dengan
 * pelepasan pembayaran, jadi ia berjalan SEKALI per task. Pengaman terakhirnya
 * unique (reference_type, reference_id, type) di `wallet_entries`: satu
 * activity paling banyak menghasilkan satu baris `earning`, berapa kali pun
 * jalur ini terpanggil.
 */
final class ApproveActivityAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder,
        private readonly WalletLedger $ledger,
    ) {}

    public function handle(Activity $activity, User $poster, ?string $note = null): Activity
    {
        return $this->db->transaction(function () use ($activity, $poster, $note): Activity {
            if (! $activity->status->canTransitionTo(ActivityStatus::Approved)) {
                throw InvalidStatusTransitionException::between(
                    $activity->status->value,
                    ActivityStatus::Approved->value,
                );
            }

            $now = now();

            $activity->forceFill([
                'status' => ActivityStatus::Approved,
                'approved_at' => $now,
                'poster_note' => $note,
            ])->save();

            // Agregat yang ditampilkan di kartu penawaran. Dinaikkan per
            // pekerja yang disetujui, bukan per task — orang ini memang sudah
            // menyelesaikan bagiannya.
            $activity->worker->workerProfileOrCreate()->increment('tasks_completed');

            $task = $activity->task;

            // Dana dilepas SEKALI, saat pekerja terakhir disetujui.
            //
            // Pembayarannya satu untuk seluruh task, jadi melepas pada
            // persetujuan pertama akan mengeluarkan seluruh dana untuk satu
            // orang — dan pekerja lain, yang pekerjaannya sudah ada di dalam
            // tagihan yang sama, tidak akan pernah bisa disetujui karena
            // pembayarannya sudah released.
            if (! $task->everyWorkerIsApproved()) {
                return $activity;
            }

            // Pelepasan pembayaran hanya berlaku kalau dananya memang pernah
            // ditahan. Selama gerbang pembayaran dimatikan, tagihannya masih
            // `pending` dan `pending → released` bukan transisi yang sah —
            // memaksakannya akan menulis "dana dilepas" untuk uang yang tidak
            // pernah masuk. Yang dilewati pelepasannya; upahnya tetap
            // dikreditkan supaya alur pekerjaan bisa ditutup ujung ke ujung.
            // Lihat config/sekarya.payments.
            $payment = $activity->payment;
            if ((bool) config('sekarya.payments.gate_enabled')) {
                if (! $payment->status->canTransitionTo(PaymentStatus::Released)) {
                    throw InvalidStatusTransitionException::between(
                        $payment->status->value,
                        PaymentStatus::Released->value,
                    );
                }
                $payment->forceFill([
                    'status' => PaymentStatus::Released,
                    'released_at' => $now,
                ])->save();
            }

            // Upah masuk ke saldo masing-masing pekerja. Dibaca dari
            // `activities`, bukan dari `bids`: penawaran bisa berubah setelah
            // diterima kalau suatu saat ada jalur yang mengizinkannya,
            // sedangkan `agreed_amount` di activity adalah angka yang menjadi
            // dasar pekerjaan ini dibuka.
            foreach ($task->activities()->with('worker')->get() as $paid) {
                $this->ledger->credit(
                    $this->ledger->walletFor($paid->worker),
                    WalletEntryType::Earning,
                    (int) $paid->agreed_amount,
                    $paid,
                    'Upah task #'.$task->task_number,
                );
            }

            $task->forceFill(['completed_at' => $now])->save();

            $this->recorder->move(
                $task,
                TaskStatus::Completed,
                ActorType::Poster,
                $poster->getKey(),
                reason: 'seluruh hasil disetujui, dana dilepas',
            );

            return $activity;
        });
    }
}
