<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Task;

use App\Actions\Task\NotifyNearbyWorkersAction;
use App\Jobs\NotifyNearbyWorkersJob;
use App\Models\Task;
use App\Models\User;
use App\Models\UserWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Mitra sekitar dicari dengan bounding box lebih dulu (indeks
 * `user_workers (latitude, longitude)`), haversine kedua — aturan CLAUDE.md
 * "Never haversine alone". Dulu seluruh pekerja tersedia dimuat lalu disaring
 * di PHP, di dalam `POST /tasks`.
 */
final class NotifyNearbyWorkersActionTest extends TestCase
{
    use RefreshDatabase;

    private const float LAT = -6.2;

    private const float LNG = 106.8;

    private function workerAt(float $lat, float $lng): User
    {
        $user = $this->activeUser();
        UserWorker::factory()->create([
            'user_id' => $user->getKey(),
            'latitude' => $lat,
            'longitude' => $lng,
            'is_available' => true,
        ]);

        return $user;
    }

    public function test_only_workers_inside_the_radius_are_notified_using_a_bounding_box(): void
    {
        Bus::fake();
        config(['sekarya.tasks.notify_radius_km' => 5]);

        $this->workerAt(self::LAT + 0.01, self::LNG);                  // ±1,1 km
        // Sudut kotak: lolos bounding box (±0,045° tiap sumbu) tapi jaraknya
        // ±7 km — haversine yang wajib menyaringnya.
        $this->workerAt(self::LAT + 0.044, self::LNG + 0.044);
        $this->workerAt(-7.25, 112.75);                                // Surabaya

        $task = Task::factory()->open()->create([
            'poster_id' => $this->activeUser()->getKey(),
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $count = app(NotifyNearbyWorkersAction::class)->handle($task);
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $this->assertSame(1, $count);
        Bus::assertDispatchedTimes(NotifyNearbyWorkersJob::class, 1);

        $workerQuery = collect($queries)->first(fn (string $sql): bool => str_contains($sql, 'from `user_workers`'));
        $this->assertNotNull($workerQuery);
        $this->assertStringContainsString('`latitude` between', $workerQuery);
        $this->assertStringContainsString('`longitude` between', $workerQuery);
    }
}
