<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Jobs\SendPushNotification;
use App\Models\Task;
use App\Models\User;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * Push tugas membawa tugas UTUH (bentuk detail, dari sudut penerima) supaya
 * aplikasi langsung memperbarui data tanpa memanggil API.
 */
final class TaskSnapshotPushTest extends TestCase
{
    use RefreshDatabase;

    private FakePushNotifier $push;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->push = new FakePushNotifier;
    }

    public function test_a_task_push_carries_the_task_as_seen_by_the_recipient(): void
    {
        $task = Task::factory()->create(['title' => 'Bersihkan taman']);

        $this->deliver($task->poster, $task, 'bid_placed');

        $data = $this->push->firstTo($task->poster)?->data ?? [];
        $snapshot = json_decode($data['task'], true);
        $this->assertSame($task->ulid, $snapshot['id']);
        $this->assertSame('Bersihkan taman', $snapshot['title']);
        $this->assertTrue($snapshot['location']['is_precise'], 'pemberi kerja melihat lokasi presisi');

        // Digambar aplikasi (data-only): satu notifikasi per tugas, diganti tiap status berubah.
        $push = $this->push->firstTo($task->poster);
        $this->assertTrue($push?->drawnByApp);
        $this->assertSame(['1', 'Judul', 'Isi'], [$data['notify'], $data['title'], $data['body']]);
    }

    public function test_a_long_task_is_gzipped_to_fit_the_fcm_limit(): void
    {
        $task = Task::factory()->create(['description' => str_repeat('Rumput tinggi di halaman belakang. ', 140)]);

        $this->deliver($task->poster, $task, 'bid_placed');

        $data = $this->push->firstTo($task->poster)?->data ?? [];
        $this->assertArrayNotHasKey('task', $data);
        $snapshot = json_decode((string) gzdecode(base64_decode($data['task_gz'])), true);
        $this->assertSame($task->ulid, $snapshot['id']);
        $this->assertLessThanOrEqual(PushSnapshot::BUDGET, strlen((string) json_encode($data)));
    }

    public function test_chat_and_silent_pushes_carry_no_task(): void
    {
        $task = Task::factory()->create();

        $this->deliver($task->poster, $task, 'chat_message');

        $push = $this->push->firstTo($task->poster);
        $this->assertArrayNotHasKey('task', $push?->data ?? []);
        $this->assertFalse($push?->drawnByApp);
    }

    /**
     * Push tanpa tugas (saldo) juga data-only: app menyimpan riwayat lonceng
     * di perangkat, jadi app harus menerimanya walau sedang di belakang.
     */
    public function test_a_push_without_task_is_drawn_by_the_app(): void
    {
        $user = Task::factory()->create()->poster;
        $message = new PushMessage('Saldo masuk', 'Isi saldo dikonfirmasi', ['type' => 'topup_confirmed']);
        (new SendPushNotification($user->getKey(), $message))->handle($this->push);

        $push = $this->push->firstTo($user);
        $this->assertTrue($push?->drawnByApp);
        $this->assertSame('1', $push?->data['notify'] ?? null);
        $this->assertSame('Saldo masuk', $push?->data['title'] ?? null);
        $this->assertArrayNotHasKey('task', $push?->data ?? []);
    }

    private function deliver(User $user, Task $task, string $type): void
    {
        $message = new PushMessage('Judul', 'Isi', ['type' => $type, 'task_id' => $task->ulid]);
        (new SendPushNotification($user->getKey(), $message))->handle($this->push);
    }
}
