<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Actions\Review\CreateReviewAction;
use App\Data\Review\CreateReviewData;
use App\Enums\ReviewerRole;
use App\Enums\ReviewTag;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\ReviewTagNotAllowedException;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * U6 (tag rating), U7 (penyaring bintang + ringkasan task per ulasan), B5
 * (ringkasan ulasan). Pencarian komentar FULLTEXT ada di ReviewSearchTest —
 * ia butuh DatabaseTruncation.
 */
final class ReviewTagsAndSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();
        $this->task = $this->completedTask($this->poster, 'Servis & Cuci AC Daikin 1 PK');
        $this->hireWorker($this->task, $this->worker);
    }

    private function completedTask(User $poster, string $title = 'Pindahan Lemari'): Task
    {
        return Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'title' => $title,
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
            'location_text' => 'Jl. Rahasia No. 7',
            'latitude' => -6.8923456,
            'longitude' => 107.6171234,
        ]);
    }

    /** Ulasan yang diterima $reviewee, lewat factory (task sendiri per ulasan). */
    private function reviewFor(User $reviewee, int $rating, ReviewerRole $role = ReviewerRole::Poster, array $extra = []): Review
    {
        return Review::factory()->create([
            'task_id' => $this->completedTask($this->activeUser())->getKey(),
            'reviewee_id' => $reviewee->getKey(),
            'reviewer_role' => $role,
            'rating' => $rating,
            ...$extra,
        ]);
    }

    // ---------------------------------------------------------------- U6

    public function test_poster_can_attach_tags_and_they_are_stored_and_returned(): void
    {
        $response = $this->asUser($this->poster)->postJson(route('v1.tasks.reviews.store', $this->task), [
            'rating' => 5,
            'tags' => ['on_time', 'tidy', 'friendly'],
            'comment' => 'Rapi dan tepat waktu',
        ])->assertCreated()
            ->assertJsonPath('data.tags', ['on_time', 'tidy', 'friendly']);

        $raw = DB::table('reviews')->where('id', $response->json('data.id'))->value('tags');
        $this->assertSame(['on_time', 'tidy', 'friendly'], json_decode((string) $raw, true));
    }

    public function test_worker_uses_the_worker_side_tag_set(): void
    {
        $this->asUser($this->worker)->postJson(route('v1.tasks.reviews.store', $this->task), [
            'rating' => 4,
            'tags' => ['clear_brief', 'on_time_payment'],
        ])->assertCreated()->assertJsonPath('data.tags', ['clear_brief', 'on_time_payment']);
    }

    public function test_a_tag_from_the_other_direction_is_rejected_per_item(): void
    {
        $this->asUser($this->poster)->postJson(route('v1.tasks.reviews.store', $this->task), [
            'rating' => 5,
            'tags' => ['tidy', 'on_time_payment'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['tags.1'])
            ->assertJsonMissingValidationErrors(['tags.0']);

        $this->asUser($this->worker)->postJson(route('v1.tasks.reviews.store', $this->task), [
            'rating' => 5,
            'tags' => ['tidy'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['tags.0']);

        $this->assertSame(0, Review::query()->where('task_id', $this->task->getKey())->count());
    }

    public function test_unknown_duplicate_and_too_many_tags_are_rejected(): void
    {
        $url = route('v1.tasks.reviews.store', $this->task);

        $this->asUser($this->poster)->postJson($url, ['rating' => 5, 'tags' => ['amazing']])
            ->assertUnprocessable()->assertJsonValidationErrors(['tags.0']);
        $this->asUser($this->poster)->postJson($url, ['rating' => 5, 'tags' => ['tidy', 'tidy']])
            ->assertUnprocessable()->assertJsonValidationErrors(['tags.1']);
        $this->asUser($this->poster)->postJson($url, ['rating' => 5, 'tags' => 'tidy'])
            ->assertUnprocessable()->assertJsonValidationErrors(['tags']);
        $this->asUser($this->poster)->postJson($url, [
            'rating' => 5,
            'tags' => ['on_time', 'tidy', 'friendly', 'skilled', 'on_time', 'tidy'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['tags']);

        $this->assertSame(0, Review::query()->where('task_id', $this->task->getKey())->count());
    }

    public function test_tags_default_to_an_empty_array_and_null_in_the_database(): void
    {
        $response = $this->asUser($this->poster)->postJson(route('v1.tasks.reviews.store', $this->task), [
            'rating' => 5,
        ])->assertCreated()
            ->assertJsonStructure(['data' => ['tags', 'task' => ['id', 'title', 'category']]])
            ->assertJsonPath('data.tags', []);

        $this->assertNull(DB::table('reviews')->where('id', $response->json('data.id'))->value('tags'));
    }

    public function test_the_action_itself_refuses_a_tag_from_the_other_direction_and_writes_nothing(): void
    {
        $this->expectException(ReviewTagNotAllowedException::class);

        try {
            app(CreateReviewAction::class)->handle(
                new CreateReviewData(5, tags: [ReviewTag::OnTimePayment]),
                $this->task,
                $this->poster,
            );
        } finally {
            $this->assertSame(0, Review::query()->where('task_id', $this->task->getKey())->count());
        }
    }

    public function test_the_dto_drops_duplicate_tags(): void
    {
        $data = new CreateReviewData(5, tags: [ReviewTag::Tidy, ReviewTag::Tidy, ReviewTag::OnTime]);

        $this->assertSame([ReviewTag::Tidy, ReviewTag::OnTime], $data->tags);
    }

    // ---------------------------------------------------------------- U7

    public function test_each_review_carries_its_task_title_and_category_without_location(): void
    {
        Review::factory()->create([
            'task_id' => $this->task->getKey(),
            'reviewer_id' => $this->poster->getKey(),
            'reviewee_id' => $this->worker->getKey(),
            'tags' => ['tidy'],
        ]);

        $response = $this->asUser($this->activeUser())
            ->getJson(route('v1.users.reviews.index', $this->worker))
            ->assertOk()
            ->assertJsonPath('data.0.task.id', $this->task->ulid)
            ->assertJsonPath('data.0.task.title', 'Servis & Cuci AC Daikin 1 PK')
            ->assertJsonPath('data.0.task.category.slug', $this->task->category->slug)
            ->assertJsonPath('data.0.tags', ['tidy']);

        $this->assertSame(['id', 'title', 'category'], array_keys($response->json('data.0.task')));
        $this->assertSame(['slug', 'name'], array_keys($response->json('data.0.task.category')));

        $body = $response->getContent();
        foreach (['Jl. Rahasia', '-6.89', '107.61', 'latitude', 'location'] as $secret) {
            $this->assertStringNotContainsString($secret, $body, $secret.' leaked');
        }
    }

    public function test_task_summary_does_not_cause_n_plus_one(): void
    {
        $this->reviewFor($this->worker, 5);
        $url = route('v1.users.reviews.index', $this->worker);
        $viewer = $this->activeUser();

        DB::enableQueryLog();
        $this->asUser($viewer)->getJson($url)->assertOk();
        $one = count(DB::getQueryLog());

        foreach (range(1, 4) as $_) {
            $this->reviewFor($this->worker, 4);
        }

        DB::flushQueryLog();
        $this->asUser($viewer)->getJson($url)->assertOk()->assertJsonCount(5, 'data');
        $five = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($one, $five, 'query count grew with the number of reviews');
    }

    public function test_rating_filters(): void
    {
        $five = $this->reviewFor($this->worker, 5);
        $four = $this->reviewFor($this->worker, 4);
        $two = $this->reviewFor($this->worker, 2);
        $one = $this->reviewFor($this->worker, 1);
        $url = route('v1.users.reviews.index', $this->worker);
        $viewer = $this->activeUser();

        $ids = fn (array $query): array => collect(
            $this->asUser($viewer)->getJson($url.'?'.http_build_query($query))->assertOk()->json('data'),
        )->pluck('id')->sort()->values()->all();

        $this->assertSame([$five->id], $ids(['rating' => 5]));
        $this->assertSame([$four->id], $ids(['rating' => 4]));
        $this->assertSame(collect([$two->id, $one->id])->sort()->values()->all(), $ids(['rating_max' => 2]));
        $this->assertSame([], $ids(['rating' => 3]));
    }

    public function test_rating_filter_validation(): void
    {
        $url = route('v1.users.reviews.index', $this->worker);
        $viewer = $this->activeUser();

        $this->asUser($viewer)->getJson($url.'?rating=6')->assertUnprocessable()->assertJsonValidationErrors(['rating']);
        $this->asUser($viewer)->getJson($url.'?rating=0')->assertUnprocessable()->assertJsonValidationErrors(['rating']);
        $this->asUser($viewer)->getJson($url.'?rating_max=9')->assertUnprocessable()->assertJsonValidationErrors(['rating_max']);
        $this->asUser($viewer)->getJson($url.'?rating=5&rating_max=2')->assertUnprocessable()->assertJsonValidationErrors(['rating']);
        $this->asUser($viewer)->getJson($url.'?q='.str_repeat('a', 101))->assertUnprocessable()->assertJsonValidationErrors(['q']);
    }

    public function test_hidden_reviews_are_never_listed_even_when_filtered(): void
    {
        $this->reviewFor($this->worker, 5, extra: ['is_visible' => false]);

        $this->asUser($this->activeUser())
            ->getJson(route('v1.users.reviews.index', $this->worker).'?rating=5')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    // ---------------------------------------------------------------- B5

    public function test_summary_counts_every_star_and_derives_average_and_percentage(): void
    {
        foreach ([5, 5, 5, 4, 2] as $rating) {
            $this->reviewFor($this->worker, $rating);
        }
        // Tidak ikut: tersembunyi, arah lain, dan milik orang lain.
        $this->reviewFor($this->worker, 1, extra: ['is_visible' => false]);
        $this->reviewFor($this->worker, 1, ReviewerRole::Worker);
        $this->reviewFor($this->poster, 1);

        $this->asUser($this->activeUser())
            ->getJson(route('v1.users.reviews.summary', $this->worker).'?role=poster')
            ->assertOk()
            ->assertExactJson(['data' => [
                'rating_avg' => 4.2,
                'rating_count' => 5,
                'distribution' => ['5' => 3, '4' => 1, '3' => 0, '2' => 1, '1' => 0],
            ]]);
    }

    public function test_summary_without_role_combines_both_directions(): void
    {
        $this->reviewFor($this->worker, 5);
        $this->reviewFor($this->worker, 3, ReviewerRole::Worker);

        $this->asUser($this->activeUser())
            ->getJson(route('v1.users.reviews.summary', $this->worker))
            ->assertOk()
            ->assertJsonPath('data.rating_count', 2)
            ->assertJsonPath('data.rating_avg', 4);
    }

    public function test_summary_for_someone_without_reviews_is_all_zero_and_distribution_is_an_object(): void
    {
        $response = $this->asUser($this->activeUser())
            ->getJson(route('v1.users.reviews.summary', $this->activeUser()).'?role=worker')
            ->assertOk()
            ->assertJsonPath('data.rating_avg', 0)
            ->assertJsonPath('data.rating_count', 0);

        // Objek dengan lima kunci, bukan larik.
        $this->assertStringContainsString(
            '"distribution":{"5":0,"4":0,"3":0,"2":0,"1":0}',
            (string) $response->getContent(),
        );
    }

    public function test_summary_matches_the_list_it_sits_above(): void
    {
        foreach ([5, 4, 4, 1] as $rating) {
            $this->reviewFor($this->worker, $rating);
        }
        $viewer = $this->activeUser();

        $summary = $this->asUser($viewer)
            ->getJson(route('v1.users.reviews.summary', $this->worker).'?role=poster')->json('data');

        foreach ([5, 4, 3, 2, 1] as $star) {
            $listed = $this->asUser($viewer)
                ->getJson(route('v1.users.reviews.index', $this->worker).'?role=poster&rating='.$star)
                ->json('data');
            $this->assertCount($summary['distribution'][(string) $star], $listed, "{$star}★ mismatch");
        }
    }

    public function test_summary_rejects_bad_role_and_requires_auth(): void
    {
        $this->asUser($this->activeUser())
            ->getJson(route('v1.users.reviews.summary', $this->worker).'?role=admin')
            ->assertUnprocessable()->assertJsonValidationErrors(['role']);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders()
            ->getJson(route('v1.users.reviews.summary', $this->worker))
            ->assertUnauthorized();
    }

    public function test_summary_query_uses_the_reviewee_index(): void
    {
        $this->reviewFor($this->worker, 5);

        $plan = implode("\n", array_map(
            static fn (object $row): string => (string) array_values((array) $row)[0],
            DB::select(
                'EXPLAIN FORMAT=TREE SELECT rating, COUNT(*) FROM reviews WHERE reviewee_id = ? AND reviewer_role = ? AND is_visible = 1 GROUP BY rating',
                [$this->worker->getKey(), 'poster'],
            ),
        ));

        $this->assertStringNotContainsString('Table scan on reviews', $plan, $plan);
        $this->assertStringContainsString('reviews_reviewee_id', $plan, $plan);
    }
}
