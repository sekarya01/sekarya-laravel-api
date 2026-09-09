<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActorType;
use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;

/**
 * Buku perekrutan sebuah task: berapa yang sudah diterima, berapa tagihannya,
 * dan kapan lelang ditutup.
 *
 * Ada sebagai kelas tersendiri karena DUA Action memakainya — menerima satu
 * pelamar, dan memulai lebih awal dengan pekerja yang sudah ada. Kalau
 * disalin ke keduanya, cepat atau lambat yang satu diperbaiki dan yang lain
 * tidak, dan angka yang melenceng di sini adalah angka yang ditagihkan.
 */
final class TaskHiring
{
    public function __construct(private readonly TaskStatusRecorder $recorder) {}

    /**
     * Hitung ULANG penghitung dan tagihan dari baris `bids`.
     *
     * Bukan increment: penghitung yang dinaikkan sedikit demi sedikit akan
     * melenceng begitu ada satu jalur yang lupa memanggilnya — dan angka ini
     * yang menentukan kapan perekrutan berhenti serta berapa yang ditagihkan.
     */
    public function sync(Task $task): void
    {
        $accepted = $task->acceptedBids()->get();

        $task->forceFill([
            'workers_hired' => $accepted->count(),
            // `agreed_amount` berarti TOTAL untuk seluruh pekerja, bukan harga
            // satu orang. Harga per orang tetap di penawaran masing-masing dan
            // disalin ke activity-nya saat dana ditahan.
            'agreed_amount' => (int) $accepted->sum('amount'),
            'bids_count' => $task->bids()->where('status', BidStatus::Pending)->count(),
        ])->save();

        // Satu tagihan per task, sebesar jumlah seluruh penawaran yang
        // diterima: pemberi kerja mentransfer sekali untuk semua orang.
        // Pembagiannya ada di `activities.agreed_amount`.
        Payment::query()->updateOrCreate(
            ['task_id' => $task->getKey()],
            [
                'payer_id' => $task->poster_id,
                'status' => PaymentStatus::Pending,
                'amount' => $task->agreed_amount,
            ],
        );
    }

    /**
     * Tutup perekrutan: sisa pelamar ditolak, task masuk status dealt.
     *
     * Pelamar yang masih menunggu HARUS ditutup di sini. Membiarkannya pending
     * berarti orang menunggu jawaban yang tidak akan pernah datang, dan feed
     * "lamaran saya" menampilkannya sebagai masih hidup.
     */
    public function close(Task $task, User $poster, string $reason): void
    {
        $task->bids()
            ->where('status', BidStatus::Pending)
            ->update([
                'status' => BidStatus::Rejected,
                'responded_at' => now(),
                'updated_at' => now(),
            ]);

        $task->forceFill(['dealt_at' => now(), 'bids_count' => 0])->save();

        $this->recorder->move(
            $task,
            TaskStatus::Dealt,
            ActorType::Poster,
            $poster->getKey(),
            reason: $reason,
        );
    }
}
