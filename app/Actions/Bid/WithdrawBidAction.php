<?php

declare(strict_types=1);

namespace App\Actions\Bid;

use App\Enums\BidStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Bid;
use App\Models\Task;
use Illuminate\Database\ConnectionInterface;

final class WithdrawBidAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(Bid $bid): Bid
    {
        return $this->db->transaction(function () use ($bid): Bid {
            // Dikunci & dibaca ulang — urutan kunci sama dengan AcceptBidAction
            // (task dulu, lalu bid) supaya tidak saling menunggu. Tanpa ini,
            // penarikan yang balapan dengan penerimaan menimpa bid `accepted`
            // jadi `withdrawn`: `workers_hired` & escrow tak lagi cocok.
            $task = Task::query()->whereKey($bid->task_id)->lockForUpdate()->firstOrFail();
            $bid = Bid::query()->whereKey($bid->getKey())->lockForUpdate()->firstOrFail();

            if (! $bid->status->isOpen()) {
                throw InvalidStatusTransitionException::between(
                    $bid->status->value,
                    BidStatus::Withdrawn->value,
                );
            }

            $bid->forceFill([
                'status' => BidStatus::Withdrawn,
                'responded_at' => now(),
            ])->save();

            $task->forceFill([
                'bids_count' => $task->pendingBidsCount(),
            ])->save();

            return $bid;
        });
    }
}
