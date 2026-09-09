<?php

declare(strict_types=1);

namespace App\Actions\Bid;

use App\Enums\BidStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\TaskAlreadyDealtException;
use App\Exceptions\Domain\TaskNotBiddableException;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskHiring;
use Illuminate\Database\ConnectionInterface;

/**
 * Pemberi kerja menerima satu pelamar.
 *
 * Task bisa merekrut banyak orang, jadi aksi ini dipanggil berulang — sekali
 * per pekerja. Setiap panggilan mengisi satu slot dan menambah tagihan task
 * sebesar penawaran orang itu.
 *
 * Pelamarnya boleh jauh lebih banyak daripada slotnya: `workers_needed` adalah
 * berapa orang yang DITERIMA, bukan berapa yang boleh melamar. Pemberi kerja
 * memilih pemenangnya dari seluruh penawaran yang masuk.
 *
 * DEAL baru terjadi saat slot TERAKHIR terisi. Sampai saat itu task tetap
 * `open`. Kalau pemberi kerja ingin berhenti lebih awal dengan jumlah yang
 * sudah ada, itu aksi terpisah — StartWithCurrentWorkersAction.
 */
final class AcceptBidAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskHiring $hiring,
    ) {}

    public function handle(Bid $bid, User $poster): Task
    {
        return $this->db->transaction(function () use ($bid, $poster): Task {
            // Row lock sungguhan: MySQL/InnoDB menerjemahkan ini ke
            // SELECT ... FOR UPDATE. Tanpa itu ada jendela di mana dua
            // penerimaan paralel sama-sama melihat slot terakhir masih kosong
            // dan merekrut 31 orang untuk 30 slot.
            $task = Task::query()->whereKey($bid->task_id)->lockForUpdate()->firstOrFail();

            // Slot habis diperiksa LEBIH DULU daripada status.
            //
            // Mengisi slot terakhir memindahkan task ke `dealt`, jadi kalau
            // status yang diperiksa duluan, percobaan merekrut orang ke-31
            // dijawab "task tidak menerima penawaran" — padahal alasannya
            // spesifik dan sudah punya kode galatnya sendiri.
            if ($task->isFullyStaffed()) {
                throw TaskAlreadyDealtException::make();
            }

            if (! $task->status->acceptsBids()) {
                throw TaskNotBiddableException::becauseStatus($task->status);
            }

            // Hanya penawaran yang masih pending bisa diterima. Tanpa ini,
            // menerima penawaran yang SAMA dua kali menaikkan jumlah pekerja
            // tanpa menambah pekerja.
            if ($bid->status !== BidStatus::Pending) {
                throw InvalidStatusTransitionException::between(
                    $bid->status->value,
                    BidStatus::Accepted->value,
                );
            }

            $bid->forceFill([
                'status' => BidStatus::Accepted,
                'responded_at' => now(),
            ])->save();

            $this->hiring->sync($task);

            $bid->bidder()->increment('bids_won');

            if ($task->isFullyStaffed()) {
                $this->hiring->close($task, $poster, sprintf(
                    'slot terpenuhi (%d pekerja)',
                    $task->workers_hired,
                ));
            }

            return $task->refresh();
        });
    }
}
