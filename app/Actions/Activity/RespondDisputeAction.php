<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Data\Activity\RespondDisputeData;
use App\Enums\DisputeStatus;
use App\Exceptions\Domain\DisputeNotAllowedException;
use App\Models\Activity;
use App\Models\TaskDispute;
use Illuminate\Database\ConnectionInterface;

/**
 * Mitra menanggapi sengketa atas hasil kerjanya — SATU kali, selama tiketnya
 * terbuka. Pengelola memutuskan sesudah mendengar kedua pihak; tanggapan
 * yang bisa ditulis ulang berkali-kali membuat yang dibaca pengelola
 * bergantung pada kapan ia membukanya.
 */
final class RespondDisputeAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(RespondDisputeData $data, Activity $activity): TaskDispute
    {
        return $this->db->transaction(function () use ($data, $activity): TaskDispute {
            $dispute = TaskDispute::query()
                ->where('activity_id', $activity->getKey())
                ->where('status', DisputeStatus::Open)
                ->lockForUpdate()
                ->first();

            if ($dispute === null) {
                throw DisputeNotAllowedException::notOpen();
            }

            if ($dispute->worker_responded_at !== null) {
                throw DisputeNotAllowedException::alreadyResponded();
            }

            $dispute->forceFill([
                'worker_response' => $data->response,
                'worker_evidence_photos' => $data->evidencePhotos === [] ? null : $data->evidencePhotos,
                'worker_responded_at' => now(),
            ])->save();

            return $dispute;
        });
    }
}
