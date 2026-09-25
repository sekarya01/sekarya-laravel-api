<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use App\Models\UserWorker;
use App\Support\GeoDistance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `distance_km` pelamar/pekerja ke lokasi task (U8).
 *
 * Tiga janji yang diuji: angkanya dihitung server; HANYA pemberi kerja task
 * itu yang mendapatkannya; dan koordinat lokasi kerja pekerja tidak pernah
 * keluar dalam bentuk apa pun.
 */
final class WorkerDistanceTest extends TestCase
{
    use RefreshDatabase;

    private const float TASK_LAT = -6.8923456;

    private const float TASK_LNG = 107.6171234;

    /** ±1,2 km dari task. Digitnya khas supaya kebocoran mudah dicari. */
    private const float WORKER_LAT = -6.9004321;

    private const float WORKER_LNG = 107.6104321;

    private User $poster;

    private Task $task;

    private User $near;

    private User $unlocated;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->task = Task::factory()->open()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'latitude' => self::TASK_LAT,
            'longitude' => self::TASK_LNG,
            'workers_needed' => 2,
        ]);

        $this->near = $this->activeUser();
        UserWorker::factory()->create([
            'user_id' => $this->near->getKey(),
            'latitude' => self::WORKER_LAT,
            'longitude' => self::WORKER_LNG,
            'radius_km' => 5,
        ]);

        // Punya profil pekerja, tapi tanpa lokasi kerja.
        $this->unlocated = $this->activeUser();
        UserWorker::factory()->create(['user_id' => $this->unlocated->getKey()]);

        $this->bid($this->near);
        $this->bid($this->unlocated);
    }

    private function bid(User $bidder, BidStatus $status = BidStatus::Pending): Bid
    {
        return Bid::factory()->create([
            'task_id' => $this->task->getKey(),
            'bidder_id' => $bidder->getKey(),
            'amount' => 150_000,
            'status' => $status,
        ]);
    }

    /** @return array<string, mixed> bidder ulid => distance_km */
    private function distancesOnBidList(User $viewer, string $sort = 'amount'): array
    {
        return collect(
            $this->asUser($viewer)
                ->getJson(route('v1.tasks.bids.index', $this->task).'?sort='.$sort)
                ->assertOk()
                ->json('data'),
        )->mapWithKeys(fn (array $bid): array => [$bid['bidder']['id'] => $bid['distance_km']])->all();
    }

    private function assertNoWorkerCoordinates(string $body): void
    {
        foreach (['6.9004', '107.6104', '6.900,', '107.61,'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, 'worker coordinate leaked: '.$leak);
        }
    }

    public function test_poster_sees_the_server_computed_distance_of_each_bidder(): void
    {
        foreach (['amount', 'rating', 'newest'] as $sort) {
            $distances = $this->distancesOnBidList($this->poster, $sort);

            $this->assertSame(1.2, $distances[$this->near->ulid], $sort);
            $this->assertNull($distances[$this->unlocated->ulid], $sort);
        }

        $response = $this->asUser($this->poster)->getJson(route('v1.tasks.bids.index', $this->task));
        $this->assertArrayHasKey('distance_km', $response->json('data.0'));
        $this->assertNoWorkerCoordinates((string) $response->getContent());
    }

    public function test_distance_is_null_when_the_task_has_no_coordinates(): void
    {
        $this->task->forceFill(['latitude' => null, 'longitude' => null, 'is_remote' => true])->save();

        $distances = $this->distancesOnBidList($this->poster);

        $this->assertNull($distances[$this->near->ulid]);
    }

    public function test_bidders_never_get_a_distance_on_their_own_paths(): void
    {
        // bids/mine
        $mine = $this->asUser($this->near)->getJson(route('v1.bids.mine'))->assertOk();
        $this->assertArrayHasKey('distance_km', $mine->json('data.0'));
        $this->assertNull($mine->json('data.0.distance_km'));
        $this->assertNoWorkerCoordinates((string) $mine->getContent());

        // my_bid di detail task
        $show = $this->asUser($this->near)->getJson(route('v1.tasks.show', $this->task->ulid))->assertOk();
        $this->assertArrayHasKey('distance_km', $show->json('data.my_bid'));
        $this->assertNull($show->json('data.my_bid.distance_km'));

        // Daftar penawaran task orang lain tetap tertutup bagi pelamar.
        $this->asUser($this->near)->getJson(route('v1.tasks.bids.index', $this->task))->assertForbidden();
    }

    public function test_workers_list_on_the_task_carries_distance_for_the_poster_only(): void
    {
        Bid::query()->where('bidder_id', $this->near->getKey())->update(['status' => BidStatus::Accepted]);
        $this->task->forceFill(['workers_hired' => 1])->save();

        $asPoster = $this->asUser($this->poster)->getJson(route('v1.tasks.show', $this->task->ulid))->assertOk();
        $this->assertSame($this->near->ulid, $asPoster->json('data.workers.0.id'));
        $this->assertSame(1.2, $asPoster->json('data.workers.0.distance_km'));
        // Profil publiknya tetap utuh di samping jaraknya.
        $this->assertArrayHasKey('as_worker', $asPoster->json('data.workers.0'));
        $this->assertNoWorkerCoordinates((string) $asPoster->getContent());

        $asWorker = $this->asUser($this->near)->getJson(route('v1.tasks.show', $this->task->ulid))->assertOk();
        $this->assertArrayHasKey('distance_km', $asWorker->json('data.workers.0'));
        $this->assertNull($asWorker->json('data.workers.0.distance_km'));
    }

    public function test_distance_adds_no_queries_per_bid(): void
    {
        DB::enableQueryLog();
        $this->asUser($this->poster)->getJson(route('v1.tasks.bids.index', $this->task))->assertOk();
        $two = count(DB::getQueryLog());

        foreach (range(1, 3) as $_) {
            $bidder = $this->activeUser();
            UserWorker::factory()->create(['user_id' => $bidder->getKey(), 'latitude' => -6.95, 'longitude' => 107.6]);
            $this->bid($bidder);
        }

        DB::flushQueryLog();
        $this->asUser($this->poster)->getJson(route('v1.tasks.bids.index', $this->task))->assertOk()->assertJsonCount(5, 'data');
        $five = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($two, $five);
    }

    public function test_worker_coordinates_are_blurred_before_the_distance_is_computed(): void
    {
        // Dua titik pekerja yang jatuh di kotak ±110 m yang sama memberi
        // jarak yang sama persis — trilaterasi tidak bisa lebih tajam dari itu.
        $a = GeoDistance::taskToWorkerKm(self::TASK_LAT, self::TASK_LNG, -6.91041, 107.61049);
        $b = GeoDistance::taskToWorkerKm(self::TASK_LAT, self::TASK_LNG, -6.90961, 107.60951);

        $this->assertSame($a, $b);
        $this->assertNull(GeoDistance::taskToWorkerKm(null, self::TASK_LNG, self::WORKER_LAT, self::WORKER_LNG));
        $this->assertNull(GeoDistance::taskToWorkerKm(self::TASK_LAT, self::TASK_LNG, self::WORKER_LAT, null));
        // Jakarta ↔ Bandung, pembanding kasar: ±117 km garis lurus.
        $this->assertEqualsWithDelta(
            117.0,
            GeoDistance::taskToWorkerKm(-6.2000000, 106.8166667, -6.9175, 107.6191),
            3.0,
        );
    }
}
