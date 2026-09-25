<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Review;
use Illuminate\Http\Request;

/** @mixin Review */
final class ReviewResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            // SELALU larik — `[]` untuk ulasan tanpa tag maupun ulasan lama
            // (kolomnya NULL). Kunci yang berganti antara null dan larik
            // memecah klien hasil generate.
            'tags' => array_values($this->tags ?? []),
            'reviewer_role' => $this->reviewer_role->value,
            'reviewer' => PublicUserResource::make($this->whenLoaded('reviewer')),
            // Pekerjaan yang dinilai ("Servis & Cuci AC Daikin 1 PK"). Sebatas
            // judul + kategori: TANPA lokasi, alamat, atau koordinat — daftar
            // ini bisa dibaca siapa pun yang login, sedangkan lokasi task
            // punya batas pengungkapannya sendiri (Task::revealsLocationTo).
            'task' => $this->whenLoaded('task', fn (): ?array => $this->task === null ? null : [
                'id' => $this->task->ulid,
                'title' => $this->task->title,
                'category' => $this->task->relationLoaded('category') && $this->task->category !== null
                    ? [
                        'slug' => $this->task->category->slug,
                        'name' => $this->task->category->name,
                    ]
                    : null,
            ]),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
