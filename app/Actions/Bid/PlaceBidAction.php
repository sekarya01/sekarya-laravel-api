<?php

declare(strict_types=1);

namespace App\Actions\Bid;

use App\Data\Bid\PlaceBidData;
use App\Enums\BidStatus;
use App\Exceptions\Domain\BidBelowMinimumException;
use App\Exceptions\Domain\CannotBidOwnTaskException;
use App\Exceptions\Domain\TaskNotBiddableException;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Ajukan penawaran.
 *
 * Jumlah pelamar TIDAK dibatasi, juga pada task yang merekrut banyak orang.
 * `workers_needed` menentukan berapa orang yang akan DITERIMA, bukan berapa
 * yang boleh melamar — pemberi kerja memilih pemenangnya dari seluruh penawaran
 * yang masuk, dan itulah gunanya lelang.
 */
final class PlaceBidAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(PlaceBidData $data, Task $task, User $bidder): Bid
    {
        return $this->db->transaction(function () use ($data, $task, $bidder): Bid {
            $this->assertBiddable($task, $bidder, $data->amount);

            // Satu orang satu penawaran: mengubah tawaran = update baris yang sama,
            // bukan menambah baris baru. Unique (task_id, bidder_id) menjaganya.
            $bid = Bid::query()->updateOrCreate(
                ['task_id' => $task->getKey(), 'bidder_id' => $bidder->getKey()],
                [
                    'amount' => $data->amount,
                    'message' => $data->message,
                    'option_responses' => $data->optionResponses === []
                        ? null
                        : $data->optionResponses,
                    'estimated_hours' => $data->estimatedHours,
                    'can_start_at' => $data->canStartAt,
                    'status' => BidStatus::Pending,
                ],
            );

            // `responded_at` dikelola sistem, bukan klien, jadi sengaja TIDAK
            // fillable — dan karena itu ia harus disetel di luar mass assignment.
            // Menaruhnya di array di atas membuatnya dibuang tanpa suara, dan
            // penawaran yang diajukan ulang setelah ditolak akan tetap membawa
            // `responded_at` lama padahal statusnya kembali pending.
            if ($bid->responded_at !== null) {
                $bid->forceFill(['responded_at' => null])->save();
            }

            $task->forceFill([
                'bids_count' => $task->bids()->where('status', BidStatus::Pending)->count(),
            ])->save();

            return $bid;
        });
    }

    private function assertBiddable(Task $task, User $bidder, int $amount): void
    {
        if ($task->poster_id === $bidder->getKey()) {
            throw CannotBidOwnTaskException::make();
        }

        if (! $task->status->acceptsBids()) {
            throw TaskNotBiddableException::becauseStatus($task->status);
        }

        if ($task->isBiddingClosed()) {
            throw TaskNotBiddableException::biddingClosed();
        }

        // budget_min adalah batas keras. budget_max SENGAJA tidak dicek:
        // poster diberi kebebasan penuh memilih, termasuk tawaran di atas anggaran.
        if ($amount < $task->budget_min) {
            throw BidBelowMinimumException::forTask($task->budget_min);
        }
    }
}
