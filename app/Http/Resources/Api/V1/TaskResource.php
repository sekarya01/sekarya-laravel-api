<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;

/** @mixin Task */
final class TaskResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $precise = $this->resource->revealsLocationTo($viewer instanceof User ? $viewer : null);
        $showsDistance = $this->resource->showsWorkerDistanceTo($viewer instanceof User ? $viewer : null);

        return [
            'id' => $this->ulid,
            'task_number' => $this->task_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'options' => $this->options ?? [],
            // Checklist pekerjaan (B10) — larik langkah; `[]` bila tidak ada.
            'checklist' => array_values($this->checklist ?? []),
            'photos' => array_values($this->photos ?? []),
            'budget' => [
                'min' => $this->budget_min,
                // null berarti tidak ada batas atas — bukan 0, bukan tak diisi.
                'max' => $this->budget_max,
                'reference_median' => $this->ref_price_median,
            ],
            // Batas pengungkapan lokasi (Task::revealsLocationTo). Sebelum
            // deal: tanpa alamat, koordinat dibulatkan 3 desimal (±110 m) —
            // cukup untuk "sejauh apa", tidak cukup untuk menemukan pintunya.
            // `area` + `city` selalu tampil: itu label kartu feed.
            'location' => [
                'text' => $precise ? $this->location_text : null,
                'area' => $this->area,
                'city' => $this->city,
                'latitude' => $this->coordinate($this->latitude, $precise),
                'longitude' => $this->coordinate($this->longitude, $precise),
                'is_precise' => $precise,
                'is_remote' => $this->is_remote,
            ],
            'bids_count' => $this->bids_count,

            // Perekrutan. `needed` sekaligus kuota pelamar: task 30 orang
            // menerima paling banyak 30 lamaran.
            'hiring' => [
                'workers_needed' => $this->workers_needed,
                'workers_hired' => $this->workers_hired,
                'slots_remaining' => $this->slotsRemaining(),
            ],

            // TOTAL yang disepakati untuk seluruh pekerja, bukan harga satu
            // orang. Harga per orang ada di `bids.amount` dan
            // `activities.agreed_amount`.
            'agreed_amount' => $this->agreed_amount,
            'needed_at' => $this->iso($this->needed_at),
            'end_at' => $this->iso($this->end_at),
            'bidding_closes_at' => $this->iso($this->bidding_closes_at),
            'dealt_at' => $this->iso($this->dealt_at),
            'completed_at' => $this->iso($this->completed_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'cancelled_by' => $this->cancelled_by,
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            // Hanya terisi kalau feed dipanggil dengan lat/lng.
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn (): ?float => round((float) $this->distance_km, 2),
            ),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'poster' => PublicUserResource::make($this->whenLoaded('poster')),
            // Terisi hanya di feed pencari kerja: null = belum dilamar.
            'my_bid' => BidResource::make($this->whenLoaded('myBid')),
            // Apakah tugas ini disimpan orang yang meminta (B11). Selalu ada
            // sebagai boolean; `false` bila tidak dimuat.
            'is_bookmarked' => (bool) ($this->bookmarked ?? false),
            // Profil publik tiap pekerja + `distance_km` (U8): jarak lokasi
            // kerjanya ke task ini, HANYA untuk pemberi kerja
            // (Task::showsWorkerDistanceTo) — `null` untuk penonton lain dan
            // bila salah satu koordinat kosong. Koordinat pekerja tidak keluar.
            'workers' => $this->whenLoaded('workers', fn (): array => $this->workers
                ->map(fn (User $worker): array => [
                    ...PublicUserResource::make($worker)->resolve($request),
                    'distance_km' => $showsDistance ? $this->resource->distanceToWorkerKm($worker) : null,
                ])
                ->all()),
            'payment' => PaymentResource::make($this->whenLoaded('payment')),
            // Permintaan pembatalan yang menunggu jawaban — hanya yang
            // `pending` (relasi `pendingCancelRequest`), supaya mobile bisa
            // memunculkan popup persetujuan di Detail Kerjaan.
            'cancel_request' => TaskCancelRequestResource::make($this->whenLoaded('pendingCancelRequest')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }

    /** Koordinat penuh untuk yang berhak, dibulatkan 3 desimal untuk selainnya. */
    private function coordinate(mixed $value, bool $precise): ?float
    {
        if ($value === null) {
            return null;
        }

        return $precise ? (float) $value : round((float) $value, 3);
    }
}
