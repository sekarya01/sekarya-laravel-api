<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Review;

use App\Actions\Review\CreateReviewAction;
use App\Actions\Review\ListUserReviewsAction;
use App\Data\CursorPageData;
use App\Data\Review\CreateReviewData;
use App\Enums\ReviewerRole;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\NotTaskParticipantException;
use App\Exceptions\Domain\ReviewNotAllowedYetException;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ReviewActionsTest extends TestCase
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
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
        $this->hireWorker($this->task, $this->worker, 220_000);
    }

    public function test_poster_reviewing_worker_records_the_right_direction(): void
    {
        $review = app(CreateReviewAction::class)
            ->handle(new CreateReviewData(5, 'Bagus'), $this->task, $this->poster);

        $this->assertSame(ReviewerRole::Poster, $review->reviewer_role);
        $this->assertSame($this->poster->getKey(), (int) $review->reviewer_id);
        $this->assertSame($this->worker->getKey(), (int) $review->reviewee_id);
        $this->assertSame(5, $review->rating);
    }

    public function test_worker_reviewing_poster_records_the_other_direction(): void
    {
        $review = app(CreateReviewAction::class)
            ->handle(new CreateReviewData(4), $this->task, $this->worker);

        $this->assertSame(ReviewerRole::Worker, $review->reviewer_role);
        $this->assertSame($this->poster->getKey(), (int) $review->reviewee_id);
    }

    /** Agregat dipisah per peran — itu inti desainnya. */
    public function test_it_updates_only_the_matching_aggregate(): void
    {
        app(CreateReviewAction::class)
            ->handle(new CreateReviewData(5), $this->task, $this->poster);

        $worker = $this->worker->refresh();
        $this->assertSame('5.00', (string) $worker->worker_rating_avg);
        $this->assertSame(1, $worker->worker_rating_count);
        // Reputasi sebagai pemberi kerja tidak tersentuh.
        $this->assertSame('0.00', (string) $worker->poster_rating_avg);
        $this->assertSame(0, $worker->poster_rating_count);
    }

    public function test_the_aggregate_is_recomputed_from_source(): void
    {
        $other = $this->activeUser();
        $second = Task::factory()->create([
            'poster_id' => $other->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
        $this->hireWorker($second, $this->worker);

        app(CreateReviewAction::class)->handle(new CreateReviewData(5), $this->task, $this->poster);
        app(CreateReviewAction::class)->handle(new CreateReviewData(3), $second, $other);

        $this->assertSame('4.00', (string) $this->worker->refresh()->worker_rating_avg);
        $this->assertSame(2, $this->worker->refresh()->worker_rating_count);
    }

    public function test_hidden_reviews_are_excluded_from_the_aggregate(): void
    {
        $review = app(CreateReviewAction::class)
            ->handle(new CreateReviewData(1), $this->task, $this->poster);
        $review->forceFill(['is_visible' => false])->save();

        // Hitung ulang lewat review kedua di task lain.
        $other = $this->activeUser();
        $second = Task::factory()->create([
            'poster_id' => $other->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
        $this->hireWorker($second, $this->worker);
        app(CreateReviewAction::class)->handle(new CreateReviewData(5), $second, $other);

        $this->assertSame('5.00', (string) $this->worker->refresh()->worker_rating_avg);
        $this->assertSame(1, $this->worker->refresh()->worker_rating_count);
    }

    public function test_reviewing_an_unfinished_task_is_rejected(): void
    {
        $this->task->forceFill(['status' => TaskStatus::Active])->save();

        try {
            app(CreateReviewAction::class)
                ->handle(new CreateReviewData(5), $this->task->refresh(), $this->poster);
            $this->fail('task belum selesai seharusnya ditolak');
        } catch (ReviewNotAllowedYetException $e) {
            $this->assertSame('review_not_allowed', $e->errorCode());
            $this->assertStringContainsString('setelah task selesai', $e->getMessage());
        }
    }

    public function test_reviewing_twice_is_rejected(): void
    {
        app(CreateReviewAction::class)->handle(new CreateReviewData(5), $this->task, $this->poster);

        try {
            app(CreateReviewAction::class)->handle(new CreateReviewData(1), $this->task, $this->poster);
            $this->fail('penilaian kedua seharusnya ditolak');
        } catch (ReviewNotAllowedYetException $e) {
            $this->assertStringContainsString('sudah memberi penilaian', $e->getMessage());
        }
    }

    /** Bukan peserta → 404, bukan 403, agar keberadaan task tidak terkonfirmasi. */
    public function test_a_stranger_gets_not_found_not_forbidden(): void
    {
        $stranger = $this->activeUser();

        try {
            app(CreateReviewAction::class)->handle(new CreateReviewData(5), $this->task, $stranger);
            $this->fail('orang luar seharusnya ditolak');
        } catch (NotTaskParticipantException $e) {
            $this->assertSame(404, $e->httpStatus());
            $this->assertSame('task_not_found', $e->errorCode());
        }
    }

    public function test_list_reviews_received_by_a_user(): void
    {
        app(CreateReviewAction::class)->handle(new CreateReviewData(5), $this->task, $this->poster);

        $page = app(ListUserReviewsAction::class)->forUser($this->worker, new CursorPageData(20));

        $this->assertCount(1, $page->items());
        $this->assertNotNull($page->first()->reviewer);
    }

    public function test_list_reviews_can_filter_by_role(): void
    {
        app(CreateReviewAction::class)->handle(new CreateReviewData(5), $this->task, $this->poster);
        app(CreateReviewAction::class)->handle(new CreateReviewData(4), $this->task, $this->worker);

        $asWorker = app(ListUserReviewsAction::class)
            ->forUser($this->worker, new CursorPageData(20), ReviewerRole::Poster);
        $asPoster = app(ListUserReviewsAction::class)
            ->forUser($this->poster, new CursorPageData(20), ReviewerRole::Worker);

        $this->assertCount(1, $asWorker->items());
        $this->assertCount(1, $asPoster->items());
    }

    public function test_list_reviews_hides_invisible_ones(): void
    {
        $review = app(CreateReviewAction::class)
            ->handle(new CreateReviewData(5), $this->task, $this->poster);
        $review->forceFill(['is_visible' => false])->save();

        $page = app(ListUserReviewsAction::class)->forUser($this->worker, new CursorPageData(20));

        $this->assertCount(0, $page->items());
    }
}
