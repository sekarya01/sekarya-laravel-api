<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Bid;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Badge `identity_verified` & `ready_to_work` di setiap pengguna yang tampil
 * (poster, worker, pekerja di detail) dibaca dari `identity_verified_count`
 * hasil `User::withIdentityVerified()`. Tanpa itu `isIdentityVerified()` jatuh
 * ke kueri `exists` — dua kali per orang karena `readyToWork()` ikut
 * memanggilnya — dan detail task 500 pekerja menjadi ±2.000 kueri.
 */
final class VerificationBadgeQueriesTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private Task $firstTask;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();

        foreach (range(1, 3) as $i) {
            $task = Task::factory()->create([
                'poster_id' => $this->poster->getKey(),
                'category_id' => $this->anyCategory()->getKey(),
                'status' => TaskStatus::Active,
                'workers_needed' => 2,
            ]);
            $this->firstTask ??= $task;
            $payment = Payment::factory()->held()->create([
                'task_id' => $task->getKey(),
                'payer_id' => $this->poster->getKey(),
            ]);

            foreach ([$this->worker, $this->activeUser()] as $worker) {
                Bid::factory()->create([
                    'task_id' => $task->getKey(),
                    'bidder_id' => $worker->getKey(),
                    'amount' => 200_000,
                    'status' => BidStatus::Accepted,
                ]);
                Activity::factory()->create([
                    'task_id' => $task->getKey(),
                    'worker_id' => $worker->getKey(),
                    'payment_id' => $payment->getKey(),
                ]);
            }

            // Feed: task terbuka milik pemberi kerja lain.
            Task::factory()->open()->create([
                'poster_id' => $this->activeUser()->getKey(),
                'category_id' => $this->anyCategory()->getKey(),
            ]);
        }
    }

    private function fallbackQueries(callable $call): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        /** @var TestResponse $response */
        $response = $call();
        $response->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return count(array_filter(
            $queries,
            static fn (string $sql): bool => str_starts_with($sql, 'select exists(select * from `user_worker_verifications`'),
        ));
    }

    public function test_no_endpoint_queries_the_badge_once_per_user(): void
    {
        $calls = [
            'feed' => fn () => $this->asUser($this->activeUser())->getJson(route('v1.tasks.index')),
            'posted' => fn () => $this->asUser($this->poster)->getJson(route('v1.tasks.posted')),
            'worked' => fn () => $this->asUser($this->worker)->getJson(route('v1.tasks.worked')),
            'activities' => fn () => $this->asUser($this->worker)->getJson(route('v1.activities.mine')),
            'detail' => fn () => $this->asUser($this->poster)->getJson(route('v1.tasks.show', $this->firstTask->ulid)),
        ];

        $this->assertSame(
            ['feed' => 0, 'posted' => 0, 'worked' => 0, 'activities' => 0, 'detail' => 0],
            array_map(fn (callable $c): int => $this->fallbackQueries($c), $calls),
        );
    }
}
