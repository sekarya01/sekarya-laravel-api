<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use App\Support\Push\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * Notifikasi push pada dua peristiwa lelang:
 *
 *  - penawaran masuk    -> pemberi kerja diberi tahu;
 *  - penawaran diterima -> pekerja yang menang diberi tahu.
 *
 * Yang diuji di sini adalah RANTAI-nya: aksi HTTP -> job -> pengirim. Jaringan
 * FCM sendiri diganti pengirim palsu; bentuk payload FCM diuji terpisah di
 * tests/Unit/Push/FcmClientTest.
 */
final class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function fakeNotifier(): FakePushNotifier
    {
        $fake = new FakePushNotifier;
        $this->app->instance(PushNotifier::class, $fake);

        return $fake;
    }

    private function openTask(User $poster): Task
    {
        return Task::factory()->open()->create([
            'poster_id' => $poster->getKey(),
            'budget_min' => 100_000,
            'budget_max' => 200_000,
            'workers_needed' => 3,
        ]);
    }

    // ── penawaran masuk -> pemberi kerja ─────────────────────────────────────

    public function test_placing_a_bid_notifies_the_task_poster(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = $this->openTask($poster);

        $fake = $this->fakeNotifier();

        $this->asUser($worker)->postJson(
            route('v1.tasks.bids.store', ['task' => $task->ulid]),
            ['amount' => 120_000],
        )->assertCreated();

        $this->assertSame(1, $fake->countTo($poster));
        $this->assertSame(0, $fake->countTo($worker), 'penawar tidak mengabari dirinya sendiri');

        $message = $fake->firstTo($poster);
        $this->assertNotNull($message);
        $this->assertSame('bid_placed', $message->data['type']);
        // Deep-link memakai id PUBLIK (ULID) — itulah yang dibaca layar detail.
        $this->assertSame($task->ulid, $message->data['task_id']);
        // Jumlah penawar ikut agar kartu di list tugas poster bisa
        // diperbarui langsung tanpa refresh — sama dengan TaskResource.
        $this->assertSame('1', $message->data['bids_count']);
    }

    /** Penawaran yang ditolak tidak menghasilkan notifikasi. */
    public function test_a_rejected_bid_does_not_notify_anyone(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = $this->openTask($poster);

        $fake = $this->fakeNotifier();

        // Di bawah budget_min -> bid_below_minimum, tidak ada baris tersimpan.
        $this->asUser($worker)->postJson(
            route('v1.tasks.bids.store', ['task' => $task->ulid]),
            ['amount' => 1],
        )->assertUnprocessable();

        $this->assertSame([], $fake->sent);
    }

    // ── penawaran diterima -> pekerja ────────────────────────────────────────

    public function test_accepting_a_bid_notifies_the_winning_worker(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = $this->openTask($poster);

        $bid = Bid::factory()->create([
            'task_id' => $task->getKey(),
            'bidder_id' => $worker->getKey(),
            'amount' => 120_000,
        ]);

        $fake = $this->fakeNotifier();

        $this->asUser($poster)->postJson(
            route('v1.bids.accept', ['bid' => $bid->ulid]),
        )->assertOk();

        $this->assertSame(1, $fake->countTo($worker));
        $this->assertSame(0, $fake->countTo($poster));

        $message = $fake->firstTo($worker);
        $this->assertNotNull($message);
        $this->assertSame('bid_accepted', $message->data['type']);
        $this->assertSame($task->ulid, $message->data['task_id']);
    }

    /**
     * Menerima penawaran yang SAMA dua kali ditolak — dan tidak mengirim
     * notifikasi kedua kalinya.
     */
    public function test_accepting_the_same_bid_twice_notifies_only_once(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = $this->openTask($poster);

        $bid = Bid::factory()->create([
            'task_id' => $task->getKey(),
            'bidder_id' => $worker->getKey(),
            'amount' => 120_000,
        ]);

        $fake = $this->fakeNotifier();

        $this->asUser($poster)->postJson(route('v1.bids.accept', ['bid' => $bid->ulid]))
            ->assertOk();

        $this->asUser($poster)->postJson(route('v1.bids.accept', ['bid' => $bid->ulid]))
            ->assertUnprocessable();

        $this->assertSame(1, $fake->countTo($worker));
    }
}
