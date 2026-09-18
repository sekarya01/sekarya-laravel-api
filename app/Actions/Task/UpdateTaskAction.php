<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Data\Task\UpdateTaskData;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\TaskNotEditableException;
use App\Exceptions\Domain\WorkersNeededBelowHiredException;
use App\Models\Category;
use App\Models\Skill;
use App\Models\Task;
use Illuminate\Database\ConnectionInterface;

/**
 * Penyuntingan isi task oleh pemiliknya.
 *
 * Hanya `draft` dan `open`. Sesudah itu isinya sudah dipakai orang lain untuk
 * memutuskan, jadi perubahannya bukan lagi penyuntingan melainkan perubahan
 * kesepakatan — dan itu jalur lain (batalkan, lalu buat ulang).
 */
final class UpdateTaskAction
{
    /** Status yang isinya masih milik pemberi kerja sepenuhnya. */
    private const EDITABLE = [TaskStatus::Draft, TaskStatus::Open];

    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(Task $task, UpdateTaskData $data): Task
    {
        if (! in_array($task->status, self::EDITABLE, true)) {
            throw TaskNotEditableException::becauseStatus($task->status);
        }

        // Dicek sebelum transaksi: penolakan ini tidak menyentuh apa pun.
        if ($data->has('workers_needed')
            && $data->workersNeeded !== null
            && $data->workersNeeded < (int) $task->workers_hired) {
            throw WorkersNeededBelowHiredException::make(
                $data->workersNeeded,
                (int) $task->workers_hired,
            );
        }

        return $this->db->transaction(function () use ($task, $data): Task {
            $attributes = [];

            // Kategori pindah berarti harga acuannya ikut pindah. Snapshot
            // ditulis ulang dengan alasan yang sama seperti saat task dibuat:
            // yang tersimpan harus angka kategori yang BERLAKU pada task ini.
            if ($data->has('category_id') && $data->categoryId !== null) {
                $category = Category::query()->findOrFail($data->categoryId);
                $attributes['category_id'] = $category->getKey();
                $attributes['ref_price_median'] = $category->ref_price_median;
            }

            foreach ([
                'title' => $data->title,
                'description' => $data->description,
                'budget_min' => $data->budgetMin,
                'budget_max' => $data->budgetMax,
                'location_text' => $data->locationText,
                'city' => $data->city,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
                'is_remote' => $data->isRemote,
                'workers_needed' => $data->workersNeeded,
                'needed_at' => $data->neededAt,
                'end_at' => $data->endAt,
                'bidding_closes_at' => $data->biddingClosesAt,
            ] as $column => $value) {
                if ($data->has($column)) {
                    $attributes[$column] = $value;
                }
            }

            // Daftar kosong berarti "kosongkan", persis seperti saat dibuat:
            // kolomnya nullable, dan `[]` di JSON tidak boleh tersimpan sebagai
            // sesuatu yang berbeda dari "tidak ada".
            if ($data->has('options')) {
                $attributes['options'] = $data->options === [] ? null : $data->options;
            }

            if ($data->has('photos')) {
                $attributes['photos'] = $data->photos === [] ? null : $data->photos;
            }

            if ($attributes !== []) {
                $task->fill($attributes)->save();
            }

            if ($data->has('skills')) {
                $task->skills()->sync(
                    Skill::query()->whereIn('slug', $data->skillSlugs)->pluck('id'),
                );
            }

            return $task->refresh();
        });
    }
}
