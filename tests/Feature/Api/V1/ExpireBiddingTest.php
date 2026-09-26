<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Models\Bid;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penutup lelang otomatis (G12): hanya task `open` yang batas waktunya lewat.
 */
final class ExpireBiddingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expires_only_open_tasks_whose_deadline_passed(): void
    {
        $this->seedReference();
        $poster = $this->activeUser();
        $bidder = $this->activeUser();

        $past = Task::factory()->open()->create([
            'poster_id' => $poster->getKey(),
            'bidding_closes_at' => now()->subMinute(),
        ]);
        Bid::factory()->create([
            'task_id' => $past->getKey(),
            'bidder_id' => $bidder->getKey(),
        ]);
        $future = Task::factory()->open()->create([
            'poster_id' => $poster->getKey(),
            'bidding_closes_at' => now()->addHour(),
        ]);
        $dealt = Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'status' => TaskStatus::Dealt,
            'bidding_closes_at' => now()->subMinute(),
        ]);

        $this->artisan('sekarya:tasks:expire-bidding')->assertSuccessful();

        $this->assertSame(TaskStatus::Expired, $past->refresh()->status);
        $this->assertSame(TaskStatus::Open, $future->refresh()->status);
        $this->assertSame(TaskStatus::Dealt, $dealt->refresh()->status);

        // Penawaran menggantung ditutup, dan penawarnya dikabari.
        $this->assertSame(BidStatus::Expired, Bid::query()->where('task_id', $past->getKey())->value('status'));

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $poster->getKey(),
            'type' => 'task_expired',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $bidder->getKey(),
            'type' => 'bid_expired',
        ]);
    }
}
