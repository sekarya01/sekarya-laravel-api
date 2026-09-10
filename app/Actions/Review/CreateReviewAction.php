<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Data\Review\CreateReviewData;
use App\Enums\ReviewerRole;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\NotTaskParticipantException;
use App\Exceptions\Domain\ReviewNotAllowedYetException;
use App\Exceptions\Domain\ReviewTargetRequiredException;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Penilaian dua arah, terikat pada satu task.
 *
 * `task_id` wajib + unique (task_id, reviewer_id, reviewee_id) adalah yang
 * membuat rating tidak bisa dipalsukan: hanya orang yang benar-benar
 * bertransaksi, satu kali per pasangan.
 *
 * Kuncinya menyertakan `reviewee_id` sejak task bisa merekrut banyak orang.
 * Dengan kunci lama (task_id, reviewer_id), pemberi kerja yang merekrut 30
 * orang hanya bisa menilai SATU dari mereka, dan 29 sisanya tidak pernah
 * mendapat rating dari pekerjaan yang benar-benar mereka kerjakan.
 */
final class CreateReviewAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(CreateReviewData $data, Task $task, User $reviewer): Review
    {
        return $this->db->transaction(function () use ($data, $task, $reviewer): Review {
            $role = $this->roleOf($task, $reviewer);

            if ($task->status !== TaskStatus::Completed) {
                throw ReviewNotAllowedYetException::taskNotCompleted();
            }

            $revieweeId = $this->revieweeFor($task, $role, $data->workerUlid);

            $exists = Review::query()
                ->where('task_id', $task->getKey())
                ->where('reviewer_id', $reviewer->getKey())
                ->where('reviewee_id', $revieweeId)
                ->exists();

            if ($exists) {
                throw ReviewNotAllowedYetException::alreadyReviewed();
            }

            $review = Review::query()->create([
                'task_id' => $task->getKey(),
                'reviewer_id' => $reviewer->getKey(),
                'reviewee_id' => $revieweeId,
                'reviewer_role' => $role,
                'rating' => $data->rating,
                'comment' => $data->comment,
            ]);

            $this->recalculateAggregate($revieweeId, $role);

            return $review;
        });
    }

    private function roleOf(Task $task, User $user): ReviewerRole
    {
        if ($user->getKey() === $task->poster_id) {
            return ReviewerRole::Poster;
        }

        $isWorker = $task->acceptedBids()
            ->where('bidder_id', $user->getKey())
            ->exists();

        // 404, bukan 403 — jangan konfirmasi keberadaan task ke orang luar.
        return $isWorker ? ReviewerRole::Worker : throw NotTaskParticipantException::make();
    }

    /**
     * Siapa yang dinilai.
     *
     * Pekerja selalu menilai pemberi kerja — tidak ada pilihan, jadi
     * `worker_id` dari klien diabaikan di jalur itu.
     *
     * Pemberi kerja menilai SEORANG pekerja. Kalau task hanya punya satu,
     * sasarannya disimpulkan supaya klien lama tetap jalan tanpa perubahan.
     * Kalau ada lebih dari satu, sasarannya WAJIB disebut: menebak berarti
     * menaruh rating pada orang yang salah, dan rating tidak bisa dicabut.
     */
    private function revieweeFor(Task $task, ReviewerRole $role, ?string $workerUlid): int
    {
        if ($role === ReviewerRole::Worker) {
            return $task->poster_id;
        }

        $workerIds = $task->acceptedBids()->pluck('bidder_id');

        if ($workerUlid === null) {
            return $workerIds->count() === 1
                ? (int) $workerIds->first()
                : throw ReviewTargetRequiredException::amongWorkers($workerIds->count());
        }

        $target = User::query()->where('ulid', $workerUlid)->value('id');

        // Orang yang tidak mengerjakan task ini dijawab sama seperti orang
        // luar — jangan ungkap siapa saja yang terlibat.
        return $target !== null && $workerIds->contains($target)
            ? (int) $target
            : throw NotTaskParticipantException::make();
    }

    /**
     * Agregat dihitung ULANG dari sumbernya, bukan ditambah secara inkremental.
     * Lebih mahal, tapi tidak bisa melenceng — dan ini angka yang dipakai
     * pemberi kerja untuk memilih orang.
     */
    private function recalculateAggregate(int $revieweeId, ReviewerRole $reviewerRole): void
    {
        $target = $reviewerRole->affectedAggregate();

        $stats = Review::query()
            ->where('reviewee_id', $revieweeId)
            ->where('reviewer_role', $reviewerRole)
            ->visible()
            ->selectRaw('COUNT(*) as c, AVG(rating) as a')
            ->first();

        $count = (int) $stats->c;
        $average = round((float) $stats->a, 2);

        // Reputasi PEKERJA tinggal di `user_workers`, reputasi PEMBERI KERJA
        // di `users`. Profil pekerjanya dibuat kalau belum ada: penilaian bisa
        // datang untuk orang yang belum pernah membuka halaman profilnya.
        if ($reviewerRole->affectsWorkerProfile()) {
            User::query()->whereKey($revieweeId)->sole()
                ->workerProfileOrCreate()
                // forceFill: agregat sengaja tidak mass-assignable.
                ->forceFill([
                    'worker_rating_count' => $count,
                    'worker_rating_avg' => $average,
                ])->save();

            return;
        }

        User::query()->whereKey($revieweeId)->update([
            "{$target}_rating_count" => $count,
            "{$target}_rating_avg" => $average,
        ]);
    }
}
