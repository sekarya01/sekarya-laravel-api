<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dispute;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\AdminAction;
use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use App\Exceptions\Domain\DisputeAlreadyResolvedException;
use App\Exceptions\Domain\DisputeNotAllowedException;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\TaskDispute;
use App\Support\AdminAuditRecorder;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;
use App\Support\TaskSettlement;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Putuskan sengketa SATU MITRA: upahnya dilepas ke mitra itu, atau
 * dikembalikan ke pemberi kerja. Mitra lain di task yang sama tidak tersentuh.
 *
 * Keterangan WAJIB — dikirim ke pemberi kerja dan mitra itu lewat notif, dan
 * dicatat di jejak audit DI DALAM transaksi (jejak keputusan yang
 * dibatalkan adalah jejak yang berbohong).
 *
 * Uang & status task lewat TaskSettlement — jalur yang sama dengan
 * persetujuan pemberi kerja, jadi tidak ada logika uang kedua.
 *
 * Tiket lama (sebelum sengketa per mitra) yang tidak bisa dipetakan ke satu
 * activity — `activity_id` NULL — berlaku untuk semua activity `rejected`
 * di task-nya, seperti aturan lama.
 *
 * Idempotensi: status tiket (`open` → `resolved`) di bawah kunci baris, dan
 * unique (reference_type, reference_id, type) di buku besar.
 */
final class ResolveDisputeAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskSettlement $settlement,
        private readonly AdminAuditRecorder $audit,
        private readonly PushDispatcher $push,
    ) {}

    public function handle(
        TaskDispute $dispute,
        Admin $admin,
        DisputeResolution $resolution,
        string $note,
        ?string $ip = null,
    ): TaskDispute {
        [$fresh, $activities] = $this->db->transaction(function () use ($dispute, $admin, $resolution, $note, $ip): array {
            $fresh = TaskDispute::query()->whereKey($dispute->getKey())->lockForUpdate()->firstOrFail();

            if (! $fresh->status->isOpen()) {
                throw DisputeAlreadyResolvedException::make();
            }

            $task = $this->settlement->lockTask($fresh->task);

            /** @var Collection<int, Activity> $activities */
            $activities = Activity::query()
                ->where('task_id', $task->getKey())
                ->when($fresh->activity_id !== null, fn ($q) => $q->whereKey($fresh->activity_id))
                ->where('status', ActivityStatus::Rejected)
                ->lockForUpdate()
                ->get();

            if ($activities->isEmpty()) {
                throw DisputeNotAllowedException::becauseStatus($fresh->activity?->status->value ?? $task->status->value);
            }

            foreach ($activities as $activity) {
                $resolution === DisputeResolution::Release
                    ? $this->settlement->approve($activity)
                    : $this->settlement->refund($activity);
            }

            $fresh->forceFill([
                'status' => DisputeStatus::Resolved,
                'resolution' => $resolution,
                'resolved_by' => $admin->getKey(),
                'resolved_at' => now(),
                'admin_note' => $note,
            ])->save();

            $this->audit->record(
                $admin,
                $resolution === DisputeResolution::Release ? AdminAction::DisputeReleased : AdminAction::DisputeRefunded,
                (int) $fresh->getKey(),
                $note,
                $ip,
            );

            $this->settlement->sync($task, ActorType::Admin, (int) $admin->getKey(), 'Sengketa: '.$resolution->label());

            return [$fresh, $activities];
        });

        // Di LUAR transaksi: pemberi kerja dan mitra yang bersengketa saja.
        $released = $resolution === DisputeResolution::Release;
        foreach ($activities as $activity) {
            $message = PushMessages::disputeResolved($fresh->task, $activity->refresh(), $released, $note);
            $this->push->send((int) $fresh->task->poster_id, $message);
            $this->push->send($activity->worker_id, $message);
        }

        return $fresh;
    }
}
