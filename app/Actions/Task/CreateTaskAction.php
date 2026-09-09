<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Data\Task\CreateTaskData;
use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Skill;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

final class CreateTaskAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(CreateTaskData $data, User $poster): Task
    {
        return $this->db->transaction(function () use ($data, $poster): Task {
            $category = Category::query()->findOrFail($data->categoryId);

            $status = $data->publishNow ? TaskStatus::Open : TaskStatus::Draft;

            $task = Task::query()->create([
                'poster_id' => $poster->getKey(),
                'category_id' => $category->getKey(),
                'title' => $data->title,
                'description' => $data->description,
                'options' => $data->options === [] ? null : $data->options,
                'photos' => $data->photos === [] ? null : $data->photos,
                'budget_min' => $data->budgetMin,
                'budget_max' => $data->budgetMax,
                // Snapshot harga referensi: angka kategori berubah seiring data masuk,
                // dan sengketa harus bisa dinilai dengan angka yang berlaku saat itu.
                'ref_price_median' => $category->ref_price_median,
                'location_text' => $data->locationText,
                'city' => $data->city,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'is_remote' => $data->isRemote,
                'workers_needed' => $data->workersNeeded,
                'needed_at' => $data->neededAt,
                'bidding_closes_at' => $data->biddingClosesAt,
                'status' => $status,
            ]);

            // Baris pertama jejak: from_status null.
            TaskStatusLog::query()->create([
                'task_id' => $task->getKey(),
                'from_status' => null,
                'to_status' => $status->value,
                'actor_type' => ActorType::Poster,
                'actor_id' => $poster->getKey(),
            ]);

            if ($data->skillSlugs !== []) {
                $task->skills()->sync(
                    Skill::query()->whereIn('slug', $data->skillSlugs)->pluck('id'),
                );
            }

            $poster->increment('tasks_posted');

            // Muat ulang supaya nilai default dari DATABASE ikut terbawa —
            // `workers_hired` dan `bids_count` tidak ada di INSERT, jadi tanpa
            // ini keduanya null pada respons pembuatan task, bukan 0.
            return $task->refresh();
        });
    }
}
