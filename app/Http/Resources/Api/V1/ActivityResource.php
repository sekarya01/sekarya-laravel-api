<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Support\GeoDistance;
use Illuminate\Http\Request;

/** @mixin Activity */
final class ActivityResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            // Checklist (B10): larik boolean sejajar `tasks.checklist`.
            'checklist_state' => array_values($this->checklist_state ?? []),
            // Catatan kemajuan terakhir (B9) — kalimat terakhir di kartu.
            'latest_update' => ActivityUpdateResource::make($this->whenLoaded('latestUpdate')),
            // Lokasi langsung + ETA (B8). Hanya selama `on_the_way`, dan hanya
            // bila lokasi task diketahui. Jarak dari koordinat task yang
            // MEMANG sudah boleh dibaca peserta ini.
            'live' => $this->when(
                $this->status === ActivityStatus::OnTheWay
                    && $this->live_latitude !== null
                    && $this->relationLoaded('task')
                    && $this->task?->latitude !== null
                    && $this->task?->longitude !== null,
                function (): array {
                    $distance = GeoDistance::betweenKm(
                        $this->live_latitude,
                        $this->live_longitude,
                        $this->task->latitude,
                        $this->task->longitude,
                    );

                    $speed = max(1.0, (float) config('sekarya.activities.eta_speed_kmh', 20));

                    return [
                        'distance_km' => $distance,
                        'eta_minutes' => $distance === null ? null : (int) ceil($distance / $speed * 60),
                        'updated_at' => $this->iso($this->live_updated_at),
                    ];
                },
            ),
            'id' => $this->ulid,
            'status' => $this->status->value,
            'agreed_amount' => $this->agreed_amount,
            'opened_at' => $this->iso($this->opened_at),
            // Kapan berangkat dan kapan tiba dipisah dari kapan mulai bekerja:
            // selisih di antaranya persis yang ditanyakan saat ada keluhan
            // "kok lama".
            'departed_at' => $this->iso($this->departed_at),
            'arrived_at' => $this->iso($this->arrived_at),
            'started_at' => $this->iso($this->started_at),
            'submitted_at' => $this->iso($this->submitted_at),
            'approved_at' => $this->iso($this->approved_at),
            'rejected_at' => $this->iso($this->rejected_at),
            'worker_note' => $this->worker_note,
            'proof_photos' => array_values($this->proof_photos ?? []),
            'poster_note' => $this->poster_note,
            'task' => TaskResource::make($this->whenLoaded('task')),
            'worker' => PublicUserResource::make($this->whenLoaded('worker')),
            'payment' => PaymentResource::make($this->whenLoaded('payment')),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
