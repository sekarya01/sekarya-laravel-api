<?php

declare(strict_types=1);

namespace App\Actions\Bid;

use App\Enums\BidStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Bid;
use Illuminate\Database\ConnectionInterface;

final class WithdrawBidAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(Bid $bid): Bid
    {
        return $this->db->transaction(function () use ($bid): Bid {
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

            $task = $bid->task;
            $task->forceFill([
                'bids_count' => $task->bids()->where('status', BidStatus::Pending)->count(),
            ])->save();

            return $bid;
        });
    }
}
