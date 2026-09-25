<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\UserNotification;
use App\Support\Push\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * Pembatalan ber-persetujuan: poster meminta, pekerja menyetujui/menolak.
 *
 * Dua jalur dipilih dari keadaan — belum deal batalkan langsung lewat
 * `POST tasks/{task}/cancel`; sudah deal lewat sini.
 */
final class TaskCancelRequestTest extends TestCase
{
    protected bool $fundUsers = true;

    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();
        $this->stranger = $this->activeUser();
    }

    private function payload(array $override = []): array
    {
        return [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Cat rumah 2 lantai',
            'description' => 'Cat ulang eksterior, bahan dari pemberi kerja.',
            'budget_min' => 150_000,
            'city' => 'Jakarta',
            'needed_at' => now()->addDays(3)->toIso8601String(),
            'publish_now' => true,
            ...$override,
        ];
    }

    private function createTask(): string
    {
        return $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload())
            ->assertCreated()
            ->json('data.id');
    }

    /** Task yang sudah deal: satu penawaran diterima, status `active`. */
    private function dealtTask(): string
    {
        $task = $this->createTask();
        $bid = $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => 200_000])
            ->json('data.id');

        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bid))->assertOk();

        return $task;
    }

    public function test_request_needs_a_dealt_task(): void
    {
        $task = $this->createTask();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task), ['reason' => 'batal'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'no_workers_hired');
    }

    public function test_poster_requests_and_worker_sees_it_on_the_task(): void
    {
        $task = $this->dealtTask();

        $requestId = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task), ['reason' => 'jadwal berubah'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.reason', 'jadwal berubah')
            ->json('data.id');

        $this->assertNotEmpty($requestId);

        // Popup pekerja dibaca dari detail task — tanpa panggilan kedua.
        $this->asUser($this->worker)
            ->getJson(route('v1.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.cancel_request.status', 'pending')
            ->assertJsonPath('data.cancel_request.reason', 'jadwal berubah');
    }

    public function test_second_request_while_pending_conflicts(): void
    {
        $task = $this->dealtTask();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task))
            ->assertCreated();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cancel_request_pending');
    }

    public function test_worker_approves_and_the_task_is_cancelled_as_poster(): void
    {
        $task = $this->dealtTask();

        $requestId = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task), ['reason' => 'batal ya'])
            ->assertCreated()
            ->json('data.id');

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.cancel-requests.approve', [$task, $requestId]))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            // Penyetuju BUKAN pembatal: tercatat atas nama pemberi kerja.
            ->assertJsonPath('data.cancelled_by', 'poster');

        $this->asUser($this->worker)
            ->getJson(route('v1.tasks.cancel-request.show', $task))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'no_pending_cancel_request');
    }

    public function test_worker_rejects_and_the_task_continues(): void
    {
        $task = $this->dealtTask();

        $requestId = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task))
            ->assertCreated()
            ->json('data.id');

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.cancel-requests.reject', [$task, $requestId]))
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->asUser($this->poster)
            ->getJson(route('v1.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.cancel_request', null);
    }

    public function test_stranger_and_poster_cannot_answer(): void
    {
        $task = $this->dealtTask();

        $requestId = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task))
            ->assertCreated()
            ->json('data.id');

        $this->asUser($this->stranger)
            ->postJson(route('v1.tasks.cancel-requests.approve', [$task, $requestId]))
            ->assertForbidden();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.approve', [$task, $requestId]))
            ->assertForbidden();
    }

    public function test_poster_withdraws_and_may_request_again(): void
    {
        $task = $this->dealtTask();

        $requestId = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task))
            ->assertCreated()
            ->json('data.id');

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.withdraw', [$task, $requestId]))
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task))
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');
    }

    public function test_answering_twice_is_a_terminal_state(): void
    {
        $task = $this->dealtTask();

        $requestId = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task))
            ->assertCreated()
            ->json('data.id');

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.cancel-requests.reject', [$task, $requestId]))
            ->assertOk();

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.cancel-requests.reject', [$task, $requestId]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'no_pending_cancel_request');
    }

    // ── notifikasi pembatalan (U12) ─────────────────────────────────────────

    private function fakeNotifier(): FakePushNotifier
    {
        $fake = new FakePushNotifier;
        $this->app->instance(PushNotifier::class, $fake);

        return $fake;
    }

    /** Permintaan pembatalan harus MENCARI pekerja, bukan menunggu ia membuka aplikasi. */
    public function test_requesting_cancel_reaches_the_worker_in_app_and_push(): void
    {
        $task = $this->dealtTask();

        $fake = $this->fakeNotifier();

        $requestId = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task), ['reason' => 'jadwal berubah'])
            ->assertCreated()
            ->json('data.id');

        $message = $fake->firstTo($this->worker);
        $this->assertNotNull($message);
        $this->assertSame('cancel_requested', $message->data['type']);
        $this->assertSame($requestId, $message->data['cancel_request_id']);

        $row = UserNotification::query()
            ->where('user_id', $this->worker->getKey())
            ->where('type', 'cancel_requested')
            ->sole();

        $this->assertSame($requestId, $row->data['cancel_request_id']);
        $this->assertNull($row->read_at);
    }

    /** Penolakan SATU pekerja sudah cukup menggugurkan permintaan — ia harus segera tahu. */
    public function test_a_rejection_reaches_the_poster_in_app_and_push(): void
    {
        $task = $this->dealtTask();

        $requestId = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task))
            ->assertCreated()
            ->json('data.id');

        $fake = $this->fakeNotifier();

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.cancel-requests.reject', [$task, $requestId]))
            ->assertOk();

        $message = $fake->firstTo($this->poster);
        $this->assertNotNull($message);
        $this->assertSame('cancel_request_resolved', $message->data['type']);
        $this->assertSame('rejected', $message->data['result']);

        $row = UserNotification::query()
            ->where('user_id', $this->poster->getKey())
            ->where('type', 'cancel_request_resolved')
            ->sole();

        $this->assertSame('rejected', $row->data['result']);
    }

    /** Saat suara terakhir setuju, task batal — pekerja kehilangan pekerjaannya. */
    public function test_the_approval_that_cancels_the_task_tells_the_worker(): void
    {
        $task = $this->dealtTask();

        $requestId = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel-requests.store', $task))
            ->assertCreated()
            ->json('data.id');

        $fake = $this->fakeNotifier();

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.cancel-requests.approve', [$task, $requestId]))
            ->assertOk();

        $message = $fake->firstTo($this->worker);
        $this->assertNotNull($message);
        $this->assertSame('task_cancelled', $message->data['type']);

        $this->assertTrue(UserNotification::query()
            ->where('user_id', $this->worker->getKey())
            ->where('type', 'task_cancelled')
            ->exists());
    }
}
