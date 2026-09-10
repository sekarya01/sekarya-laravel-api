<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Review;

use App\Actions\Review\CreateReviewAction;
use App\Actions\Task\ListTasksAction;
use App\Data\CursorPageData;
use App\Data\Review\CreateReviewData;
use App\Data\Task\ListTasksData;
use App\Enums\ReviewerRole;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\NotTaskParticipantException;
use App\Exceptions\Domain\ReviewNotAllowedYetException;
use App\Exceptions\Domain\ReviewTargetRequiredException;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penilaian ketika satu task punya banyak pekerja.
 *
 * Dengan kunci lama (task_id, reviewer_id), pemberi kerja yang merekrut tiga
 * puluh orang hanya bisa menilai SATU dari mereka — dua puluh sembilan sisanya
 * tidak pernah mendapat rating dari pekerjaan yang benar-benar mereka kerjakan.
 * Kuncinya kini menyertakan siapa yang dinilai, dan sasarannya harus disebut.
 */
final class MultiWorkerReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    /** @var list<User> */
    private array $workers = [];

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
            'workers_needed' => 3,
        ]);

        foreach (range(1, 3) as $i) {
            $worker = $this->activeUser();
            $this->workers[] = $worker;
            $this->hireWorker($this->task, $worker, 100_000 * $i);
        }

        $this->task->refresh();
    }

    private function review(User $reviewer, int $rating, ?User $target = null): Review
    {
        return app(CreateReviewAction::class)->handle(
            new CreateReviewData($rating, workerUlid: $target?->ulid),
            $this->task,
            $reviewer,
        );
    }

    public function test_the_poster_can_review_every_hired_worker(): void
    {
        foreach ($this->workers as $i => $worker) {
            $this->review($this->poster, $i + 3, $worker);
        }

        $this->assertSame(3, Review::query()
            ->where('task_id', $this->task->getKey())
            ->where('reviewer_id', $this->poster->getKey())
            ->count());

        foreach ([3, 4, 5] as $i => $rating) {
            $reviewed = $this->reputationOf($this->workers[$i]);
            $this->assertSame(1, $reviewed->worker_rating_count);
            $this->assertSame($rating, (int) $reviewed->worker_rating_avg);
        }
    }

    /**
     * Menebak sasaran berarti menaruh rating pada orang yang salah, dan rating
     * tidak bisa dicabut kembali.
     */
    public function test_the_poster_must_name_which_worker_when_there_is_more_than_one(): void
    {
        try {
            $this->review($this->poster, 5);
            $this->fail('menilai tanpa menyebut pekerja seharusnya ditolak');
        } catch (ReviewTargetRequiredException $e) {
            $this->assertSame('review_target_required', $e->errorCode());
            $this->assertSame(['worker_count' => 3], $e->context());
        } finally {
            $this->assertSame(0, Review::query()->where('task_id', $this->task->getKey())->count());
        }
    }

    /** Task satu orang: sasarannya jelas, klien lama tetap jalan apa adanya. */
    public function test_a_single_worker_task_still_needs_no_target(): void
    {
        $solo = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Completed,
            'completed_at' => now(),
        ]);
        $only = $this->activeUser();
        $this->hireWorker($solo, $only);

        $review = app(CreateReviewAction::class)->handle(
            new CreateReviewData(5),
            $solo->refresh(),
            $this->poster,
        );

        $this->assertSame($only->getKey(), (int) $review->reviewee_id);
        $this->assertSame(ReviewerRole::Poster, $review->reviewer_role);
    }

    /** Orang yang tidak mengerjakan task ini dijawab sama seperti orang luar. */
    public function test_reviewing_someone_who_did_not_work_on_it_is_refused(): void
    {
        $outsider = $this->activeUser();

        $this->expectException(NotTaskParticipantException::class);

        $this->review($this->poster, 5, $outsider);
    }

    public function test_reviewing_the_same_worker_twice_is_refused(): void
    {
        $this->review($this->poster, 5, $this->workers[0]);

        try {
            $this->review($this->poster, 1, $this->workers[0]);
            $this->fail('penilaian kedua untuk orang yang sama seharusnya ditolak');
        } catch (ReviewNotAllowedYetException $e) {
            $this->assertSame('review_not_allowed', $e->errorCode());
        } finally {
            // Yang gagal tidak boleh menggeser rating yang sudah ada.
            $this->assertSame(5, (int) $this->reputationOf($this->workers[0])->worker_rating_avg);
        }
    }

    /** Arah sebaliknya: setiap pekerja menilai pemberi kerja yang sama. */
    public function test_every_worker_can_review_the_poster(): void
    {
        foreach ($this->workers as $worker) {
            $review = $this->review($worker, 4);
            $this->assertSame($this->poster->getKey(), (int) $review->reviewee_id);
            $this->assertSame(ReviewerRole::Worker, $review->reviewer_role);
        }

        $this->assertSame(3, $this->poster->refresh()->poster_rating_count);
    }

    /** Sasaran yang dikirim pekerja diabaikan — arahnya hanya satu. */
    public function test_a_worker_cannot_redirect_their_review_at_another_worker(): void
    {
        $review = $this->review($this->workers[0], 5, $this->workers[1]);

        $this->assertSame($this->poster->getKey(), (int) $review->reviewee_id);
    }

    // ── Otorisasi & feed ────────────────────────────────────────────────────

    public function test_every_hired_worker_counts_as_a_participant(): void
    {
        $policy = app(TaskPolicy::class);

        foreach ($this->workers as $worker) {
            $this->assertTrue($policy->view($worker, $this->task));
            $this->assertTrue($policy->review($worker, $this->task));
            $this->assertTrue($policy->cancel($worker, $this->task));
        }

        $outsider = $this->activeUser();
        $this->assertFalse($policy->review($outsider, $this->task));
        $this->assertFalse($policy->cancel($outsider, $this->task));
    }

    public function test_the_task_appears_in_every_hired_workers_feed(): void
    {
        foreach ($this->workers as $worker) {
            $ids = app(ListTasksAction::class)
                ->workedBy(new ListTasksData(page: new CursorPageData(20)), $worker)
                ->pluck('id')->map(intval(...))->all();

            $this->assertContains($this->task->getKey(), $ids);
            $this->assertContains($this->task->getKey(), $worker->workedTasks()->pluck('tasks.id')
                ->map(intval(...))->all());
        }

        $outsider = $this->activeUser();
        $this->assertNotContains(
            $this->task->getKey(),
            app(ListTasksAction::class)
                ->workedBy(new ListTasksData(page: new CursorPageData(20)), $outsider)
                ->pluck('id')->map(intval(...))->all(),
        );
    }
}
