<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Enums\ActivityStatus;
use App\Enums\DisputeCategory;
use App\Enums\TaskStatus;
use App\Jobs\SendPushNotification;
use App\Models\Activity;
use App\Models\Task;
use App\Models\TaskDispute;
use App\Models\User;
use App\Support\Push\PushMessage;
use App\Support\Push\PushSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * Dua event push yang BERDIRI SENDIRI (2026-10-11):
 *
 * - `event=task` membawa `task_update`: task dari sudut penerima TANPA daftar
 *   pekerja/activity — biayanya tetap, berapa pun pekerjanya. Dulu snapshot
 *   detail LENGKAP dibangun per penerima lalu dibuang karena >4 KB.
 * - `event=work` membawa `work_update`: SATU activity (status pekerja, waktu,
 *   catatan, foto, sengketa) + `task_status`. Aplikasi mengganti baris pekerja
 *   itu saja.
 *
 * Kunci lama `task` sengaja tidak dipakai lagi: APK lama yang menerimanya
 * akan menimpa seluruh task (dan menghapus daftar pekerjanya) dengan data
 * parsial. Tanpa kunci itu APK lama memuat ulang dari API — tetap benar.
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

    public function test_a_task_event_carries_the_task_without_workers(): void
    {
        $task = Task::factory()->create(['title' => 'Bersihkan taman']);

        $this->deliver($task->poster, $task, 'bid_placed', 'task');

        $data = $this->push->firstTo($task->poster)?->data ?? [];
        $this->assertSame('task', $data['event']);
        $this->assertArrayNotHasKey('task', $data);
        $snapshot = json_decode($data['task_update'], true);
        $this->assertSame($task->ulid, $snapshot['id']);
        $this->assertSame('Bersihkan taman', $snapshot['title']);
        $this->assertTrue($snapshot['location']['is_precise'], 'pemberi kerja melihat lokasi presisi');
        $this->assertArrayNotHasKey('workers', $snapshot);
        $this->assertArrayNotHasKey('activities', $snapshot);

        // Digambar aplikasi (data-only): satu notifikasi per tugas, diganti tiap status berubah.
        $push = $this->push->firstTo($task->poster);
        $this->assertTrue($push?->drawnByApp);
        $this->assertSame(['1', 'Judul', 'Isi'], [$data['notify'], $data['title'], $data['body']]);
    }

    /** Biaya event tugas tidak tumbuh dengan jumlah pekerja (masalah #12). */
    public function test_the_task_event_cost_does_not_grow_with_the_number_of_workers(): void
    {
        $small = $this->taskWithWorkers(1);
        $large = $this->taskWithWorkers(15);

        // Yang mahal bukan jumlah kueri (eager load tetap satu kueri per
        // relasi) melainkan baris yang dimuat & diserialisasi: dulu seluruh
        // pekerja + activity ikut dibangun untuk tiap penerima.
        $this->assertSame(
            $this->usersLoadedToDeliver($small),
            $this->usersLoadedToDeliver($large),
        );
    }

    public function test_a_work_event_carries_only_that_workers_activity(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::Active]);
        $activity = Activity::factory()->create([
            'task_id' => $task->getKey(),
            'status' => ActivityStatus::Submitted,
            'worker_note' => 'Sudah beres',
        ]);

        $this->deliver($task->poster, $task, 'activity_submitted', 'work', $activity);

        $data = $this->push->firstTo($task->poster)?->data ?? [];
        $this->assertSame('work', $data['event']);
        $this->assertSame('active', $data['task_status']);
        // Ringkasan tugas datar untuk panel kartu lonceng di aplikasi.
        $this->assertSame($task->title, $data['task_title']);
        $this->assertSame($task->category->name, $data['category_name']);
        $this->assertSame((string) $task->category->icon, $data['category_icon']);
        $this->assertArrayNotHasKey('task_update', $data);
        $work = json_decode($data['work_update'], true);
        $this->assertSame($activity->ulid, $work['id']);
        $this->assertSame('submitted', $work['status']);
        $this->assertSame('Sudah beres', $work['worker_note']);
        $this->assertSame($activity->worker->ulid, $work['worker']['id']);
        $this->assertArrayNotHasKey('task', $work, 'tanpa task bersarang — itu event lain');
        $this->assertArrayNotHasKey('dispute', $work);
    }

    public function test_a_work_event_carries_the_dispute_of_that_activity(): void
    {
        $task = Task::factory()->create(['status' => TaskStatus::Disputed]);
        $activity = Activity::factory()->create([
            'task_id' => $task->getKey(),
            'status' => ActivityStatus::Rejected,
        ]);
        $dispute = new TaskDispute;
        $dispute->forceFill([
            'task_id' => $task->getKey(),
            'activity_id' => $activity->getKey(),
            'raised_by' => $task->poster_id,
            'category' => DisputeCategory::Incomplete,
            'reason' => 'Kamar mandi belum dibersihkan.',
        ])->save();

        $this->deliver($activity->worker, $task, 'activity_rejected', 'work', $activity);

        $work = json_decode($this->push->firstTo($activity->worker)?->data['work_update'] ?? '{}', true);
        $this->assertSame($dispute->refresh()->ulid, $work['dispute']['id']);
        $this->assertSame('Kamar mandi belum dibersihkan.', $work['dispute']['reason']);
    }

    public function test_a_long_task_is_gzipped_to_fit_the_fcm_limit(): void
    {
        $task = Task::factory()->create(['description' => str_repeat('Rumput tinggi di halaman belakang. ', 140)]);

        $this->deliver($task->poster, $task, 'bid_placed', 'task');

        $data = $this->push->firstTo($task->poster)?->data ?? [];
        $this->assertArrayNotHasKey('task_update', $data);
        $snapshot = json_decode((string) gzdecode(base64_decode($data['task_update_gz'])), true);
        $this->assertSame($task->ulid, $snapshot['id']);
        $this->assertLessThanOrEqual(PushSnapshot::BUDGET, strlen((string) json_encode($data)));
    }

    public function test_chat_and_silent_pushes_carry_no_snapshot(): void
    {
        $task = Task::factory()->create();

        $this->deliver($task->poster, $task, 'chat_message', null);

        $push = $this->push->firstTo($task->poster);
        $this->assertArrayNotHasKey('task_update', $push?->data ?? []);
        $this->assertArrayNotHasKey('work_update', $push?->data ?? []);
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
        $this->assertArrayNotHasKey('task_update', $push?->data ?? []);
    }

    private function deliver(User $user, Task $task, string $type, ?string $event, ?Activity $activity = null): void
    {
        $data = ['type' => $type, 'task_id' => $task->ulid];
        if ($event !== null) {
            $data['event'] = $event;
        }
        if ($activity !== null) {
            $data['activity_id'] = $activity->ulid;
            $data['activity_status'] = $activity->status->value;
        }

        (new SendPushNotification($user->getKey(), new PushMessage('Judul', 'Isi', $data)))->handle($this->push);
    }

    private function taskWithWorkers(int $count): Task
    {
        $task = Task::factory()->create(['status' => TaskStatus::Active, 'workers_needed' => $count]);
        Activity::factory()->count($count)->create(['task_id' => $task->getKey()]);

        return $task;
    }

    private function usersLoadedToDeliver(Task $task): int
    {
        $loaded = 0;
        Event::listen('eloquent.retrieved: '.User::class, function () use (&$loaded): void {
            $loaded++;
        });
        $this->deliver($task->poster, $task, 'task_cancelled', 'task');
        Event::forget('eloquent.retrieved: '.User::class);

        return $loaded;
    }
}
