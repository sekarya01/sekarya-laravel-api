<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `schedule:run` sendiri — bukan command-nya — benar-benar memproses data.
 *
 * Di produksi `schedule:run` pernah menulis DONE tanpa menutup satu task pun
 * (proses anak gagal diam-diam), sementara test command langsung tetap hijau.
 * Test ini menjaga jalur yang dipakai cron.
 */
final class ScheduleRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_scheduled_task_runs_in_process(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertEqualsCanonicalizing([
            'sekarya:tasks:expire-bidding',
            'sekarya:chat:purge-expired',
            'sekarya:activities:auto-approve',
        ], $events->map->description->all());

        // Tanpa proses anak: tidak ada yang bisa gagal diam-diam di luar log.
        $events->each(fn ($event) => $this->assertInstanceOf(CallbackEvent::class, $event));
    }

    public function test_schedule_run_expires_an_overdue_open_task(): void
    {
        $this->seedReference();
        $poster = $this->activeUser();

        $task = Task::factory()->open()->create([
            'poster_id' => $poster->getKey(),
            'needed_at' => now()->subDay(),
        ]);

        // Menit kelipatan 5: `expire-bidding` jatuh tempo.
        $this->travelTo(now()->startOfHour()->addMinutes(5));

        $this->artisan('schedule:run')
            ->expectsOutputToContain('Lelang ditutup: 1 task.')
            ->assertSuccessful();

        $this->assertSame(TaskStatus::Expired, $task->refresh()->status);
    }
}
