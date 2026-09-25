<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BidStatus;
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
 * Batas pengungkapan lokasi task (U2).
 *
 * UI menjanjikan "detail alamat hanya dibagikan setelah Mitra disetujui".
 * Janji itu hanya benar kalau SERVER yang menahannya — aplikasi bisa
 * menyembunyikan teksnya, tapi JSON mentahnya tetap bisa dibaca siapa pun.
 * Karena itu yang diuji di sini adalah isi respons utuh, di setiap endpoint
 * yang menyematkan task.
 */
final class TaskLocationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private const string ADDRESS = 'Jl. Rahasia No. 7, RT 03/RW 05, lantai 2';

    private const float LAT = -6.8923456;

    private const float LNG = 107.6171234;

    private User $poster;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->task = $this->openTask($this->poster);
    }

    private function openTask(User $poster): Task
    {
        return Task::factory()->open()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'location_text' => self::ADDRESS,
            'area' => 'Coblong',
            'city' => 'Kota Bandung',
            'latitude' => self::LAT,
            'longitude' => self::LNG,
        ]);
    }

    private function bid(Task $task, User $bidder, BidStatus $status): Bid
    {
        return Bid::factory()->create([
            'task_id' => $task->getKey(),
            'bidder_id' => $bidder->getKey(),
            'amount' => 220_000,
            'status' => $status,
        ]);
    }

    private function assertMasked(TestResponse $response, string $path): void
    {
        $response->assertOk();

        // Kuncinya HARUS ada: assertJsonPath(..., null) juga lulus saat kunci hilang.
        $this->assertSame(
            ['text', 'area', 'city', 'latitude', 'longitude', 'is_precise', 'is_remote'],
            array_keys((array) $response->json($path)),
        );

        $response
            ->assertJsonPath($path.'.text', null)
            ->assertJsonPath($path.'.is_precise', false)
            ->assertJsonPath($path.'.area', 'Coblong')
            ->assertJsonPath($path.'.city', 'Kota Bandung')
            ->assertJsonPath($path.'.latitude', -6.892)
            ->assertJsonPath($path.'.longitude', 107.617);

        // Seluruh badan, bukan hanya kuncinya: relasi bersarang bisa membocorkan.
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('Jl. Rahasia', $body);
        $this->assertStringNotContainsString('6.8923456', $body);
        $this->assertStringNotContainsString('107.6171234', $body);
    }

    private function assertPrecise(TestResponse $response, string $path): void
    {
        $response->assertOk()
            ->assertJsonPath($path.'.text', self::ADDRESS)
            ->assertJsonPath($path.'.is_precise', true)
            ->assertJsonPath($path.'.latitude', self::LAT)
            ->assertJsonPath($path.'.longitude', self::LNG);
    }

    // ── Yang belum deal: tersamar ──────────────────────────────────────────

    public function test_a_stranger_sees_a_masked_location_in_the_feed(): void
    {
        $stranger = $this->activeUser();

        $response = $this->asUser($stranger)->getJson(route('v1.tasks.index'));

        $this->assertMasked($response, 'data.0.location');
    }

    public function test_a_stranger_sees_a_masked_location_in_the_detail(): void
    {
        $this->assertMasked(
            $this->asUser($this->activeUser())->getJson(route('v1.tasks.show', $this->task->ulid)),
            'data.location',
        );
    }

    /** Menawar belum berarti disetujui. */
    public function test_a_pending_bidder_still_sees_a_masked_location(): void
    {
        $bidder = $this->activeUser();
        $this->bid($this->task, $bidder, BidStatus::Pending);

        $this->assertMasked(
            $this->asUser($bidder)->getJson(route('v1.tasks.show', $this->task->ulid)),
            'data.location',
        );
        $this->assertMasked(
            $this->asUser($bidder)->getJson(route('v1.bids.mine')),
            'data.0.task.location',
        );
    }

    public function test_a_rejected_bidder_sees_a_masked_location(): void
    {
        $bidder = $this->activeUser();
        $this->bid($this->task, $bidder, BidStatus::Rejected);

        $this->assertMasked(
            $this->asUser($bidder)->getJson(route('v1.bids.mine')),
            'data.0.task.location',
        );
    }

    /**
     * Pekerja yang sudah deal di task LAIN tidak mendapat lokasi task ini —
     * penentunya penawaran pada task yang sedang dibaca, bukan status orangnya.
     */
    public function test_being_hired_elsewhere_does_not_reveal_this_task(): void
    {
        $worker = $this->activeUser();
        $this->hireWorker($this->openTask($this->activeUser()), $worker);

        $this->assertMasked(
            $this->asUser($worker)->getJson(route('v1.tasks.show', $this->task->ulid)),
            'data.location',
        );
    }

    // ── Pemberi kerja & pekerja yang sudah deal: presisi ───────────────────

    public function test_the_poster_sees_the_precise_location(): void
    {
        $this->assertPrecise(
            $this->asUser($this->poster)->getJson(route('v1.tasks.show', $this->task->ulid)),
            'data.location',
        );
        $this->assertPrecise(
            $this->asUser($this->poster)->getJson(route('v1.tasks.posted')),
            'data.0.location',
        );
    }

    public function test_a_hired_worker_sees_the_precise_location_everywhere(): void
    {
        $worker = $this->activeUser();
        $this->hireWorker($this->task, $worker);

        $this->assertPrecise(
            $this->asUser($worker)->getJson(route('v1.tasks.show', $this->task->ulid)),
            'data.location',
        );
        $this->assertPrecise(
            $this->asUser($worker)->getJson(route('v1.tasks.worked')),
            'data.0.location',
        );
        $this->assertPrecise(
            $this->asUser($worker)->getJson(route('v1.bids.mine')),
            'data.0.task.location',
        );
    }

    public function test_a_hired_worker_sees_the_precise_location_on_activities(): void
    {
        $worker = $this->activeUser();
        $this->hireWorker($this->task, $worker);
        $activity = $this->activityFor($this->task, $worker);

        $this->assertPrecise(
            $this->asUser($worker)->getJson(route('v1.activities.mine')),
            'data.0.task.location',
        );
        $this->assertPrecise(
            $this->asUser($worker)->getJson(route('v1.activities.show', $activity->ulid)),
            'data.task.location',
        );
    }

    /** Satu pekerja deal tidak membuka lokasi untuk pelamar lain di task yang sama. */
    public function test_one_hired_worker_does_not_reveal_it_to_other_bidders(): void
    {
        $this->hireWorker($this->task, $this->activeUser());
        $other = $this->activeUser();
        $this->bid($this->task, $other, BidStatus::Pending);

        $this->assertMasked(
            $this->asUser($other)->getJson(route('v1.tasks.show', $this->task->ulid)),
            'data.location',
        );
    }

    /** Task tanpa koordinat tetap null, bukan 0. */
    public function test_missing_coordinates_stay_null_when_masked(): void
    {
        $this->task->forceFill(['latitude' => null, 'longitude' => null])->save();

        $this->asUser($this->activeUser())->getJson(route('v1.tasks.show', $this->task->ulid))
            ->assertOk()
            ->assertJsonPath('data.location.latitude', null)
            ->assertJsonPath('data.location.longitude', null)
            ->assertJsonPath('data.location.is_precise', false);
    }

    // ── Tanpa N+1 ──────────────────────────────────────────────────────────

    /**
     * Penentu lokasi dibaca dari relasi `myBid` yang sudah dimuat, bukan dari
     * kueri `exists` per baris.
     *
     * Yang dihitung HANYA kueri cadangan milik Task::revealsLocationTo, bukan
     * total kueri: jumlah total ikut naik karena N+1 lain yang sudah ada
     * sebelumnya (lencana verifikasi di PublicUserResource), dan menguji total
     * akan menyalahkan perubahan ini atas hal yang bukan miliknya.
     *
     * @param  callable(): TestResponse  $call
     */
    private function locationFallbackQueries(callable $call): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $call()->assertOk();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        return count(array_filter(
            $queries,
            static fn (string $sql): bool => str_starts_with($sql, 'select exists(select * from `bids`'),
        ));
    }

    public function test_the_lists_do_not_run_one_query_per_task(): void
    {
        $worker = $this->activeUser();

        foreach (range(1, 4) as $_) {
            $task = $this->openTask($this->activeUser());
            $this->hireWorker($task, $worker);
            $this->activityFor($task, $worker);
        }

        $calls = [
            'feed' => fn () => $this->asUser($this->activeUser())->getJson(route('v1.tasks.index')),
            'posted' => fn () => $this->asUser($this->poster)->getJson(route('v1.tasks.posted')),
            'worked' => fn () => $this->asUser($worker)->getJson(route('v1.tasks.worked')),
            'bids' => fn () => $this->asUser($worker)->getJson(route('v1.bids.mine')),
            'activities' => fn () => $this->asUser($worker)->getJson(route('v1.activities.mine')),
        ];

        $this->assertSame(
            ['feed' => 0, 'posted' => 0, 'worked' => 0, 'bids' => 0, 'activities' => 0],
            array_map(fn (callable $c): int => $this->locationFallbackQueries($c), $calls),
        );
    }

    // ── area ditulis lewat API ─────────────────────────────────────────────

    public function test_area_is_written_on_create_and_update(): void
    {
        $poster = $this->activeUser();
        $this->fundWallet($poster, 10_000_000);

        $id = $this->asUser($poster)->postJson(route('v1.tasks.store'), [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Pindahan lemari',
            'description' => 'Lemari dua pintu ke lantai dua.',
            'budget_min' => 150_000,
            'city' => 'Kota Bandung',
            'area' => 'Coblong',
            'location_text' => self::ADDRESS,
            'needed_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()
            ->assertJsonPath('data.location.area', 'Coblong')
            ->assertJsonPath('data.location.is_precise', true)
            ->json('data.id');

        $this->assertSame('Coblong', Task::query()->where('ulid', $id)->value('area'));

        $this->asUser($poster)->putJson(route('v1.tasks.update', $id), ['area' => 'Dago'])
            ->assertOk()
            ->assertJsonPath('data.location.area', 'Dago');

        $this->assertSame('Dago', Task::query()->where('ulid', $id)->value('area'));
    }

    public function test_area_is_limited_to_80_characters(): void
    {
        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $this->task->ulid), ['area' => str_repeat('a', 81)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('area');
    }

    private function activityFor(Task $task, User $worker): Activity
    {
        return Activity::factory()->create([
            'task_id' => $task->getKey(),
            'worker_id' => $worker->getKey(),
            'payment_id' => Payment::factory()->held()->create([
                'task_id' => $task->getKey(),
                'payer_id' => $task->poster_id,
            ])->getKey(),
        ]);
    }
}
