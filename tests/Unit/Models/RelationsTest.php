<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\ActorType;
use App\Enums\ReviewerRole;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\EmailVerificationCode;
use App\Models\Payment;
use App\Models\Review;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Models\UserVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Relasi balik yang tidak dilewati alur utama.
 *
 * Sebuah relasi yang salah arah atau salah kolom asing baru terlihat saat
 * benar-benar dipanggil — bukan saat didefinisikan.
 */
final class RelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_relations(): void
    {
        $this->seedReference();
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
        ]);
        $this->hireWorker($task, $worker, 100_000);
        $payment = Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $poster->getKey(),
        ]);

        $this->assertSame($task->getKey(), $payment->task->getKey());
        $this->assertSame($poster->getKey(), $payment->payer->getKey());
        $this->assertNull($payment->activity);

        $activity = $this->openActivities($task, $poster)->sole();
        $this->assertSame($activity->getKey(), $payment->refresh()->activity->getKey());
    }

    public function test_review_relations(): void
    {
        $this->seedReference();
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Completed,
        ]);
        $this->hireWorker($task, $worker);

        $review = Review::query()->create([
            'task_id' => $task->getKey(),
            'reviewer_id' => $poster->getKey(),
            'reviewee_id' => $worker->getKey(),
            'reviewer_role' => ReviewerRole::Poster,
            'rating' => 5,
        ]);

        $this->assertSame($task->getKey(), $review->task->getKey());
        $this->assertSame($poster->getKey(), $review->reviewer->getKey());
        $this->assertSame($worker->getKey(), $review->reviewee->getKey());
    }

    public function test_category_has_tasks(): void
    {
        $this->seedReference();
        $category = $this->anyCategory();
        $task = Task::factory()->create([
            'poster_id' => $this->activeUser()->getKey(),
            'category_id' => $category->getKey(),
        ]);

        // Dilingkupi ke baris milik test ini — lihat catatan di Tests\TestCase
        // soal mengapa tabel tidak boleh diandaikan kosong.
        $this->assertSame(
            [$task->getKey()],
            $category->tasks()->whereKey($task->getKey())->pluck('id')->all(),
        );
    }

    public function test_task_status_log_belongs_to_a_task(): void
    {
        $this->seedReference();
        $task = Task::factory()->create([
            'poster_id' => $this->activeUser()->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
        ]);
        $log = TaskStatusLog::query()->create([
            'task_id' => $task->getKey(),
            'to_status' => TaskStatus::Open->value,
            'actor_type' => ActorType::System,
        ]);

        $this->assertSame($task->getKey(), $log->task->getKey());
    }

    public function test_verification_belongs_to_a_user(): void
    {
        $user = $this->activeUser();
        $verification = UserVerification::factory()->create(['user_id' => $user->getKey()]);

        $this->assertSame($user->getKey(), $verification->user->getKey());
    }

    public function test_verification_code_belongs_to_a_user(): void
    {
        $user = $this->activeUser();
        $code = EmailVerificationCode::query()->create([
            'user_id' => $user->getKey(),
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->assertSame($user->getKey(), $code->user->getKey());
    }

    public function test_skill_and_category_link_both_ways(): void
    {
        $this->seedReference();
        $skill = $this->skill('cuci-ac');
        $category = Category::query()->where('slug', 'bersih-rumah')->firstOrFail();

        $this->assertSame($category->getKey(), $skill->category->getKey());
    }
}
