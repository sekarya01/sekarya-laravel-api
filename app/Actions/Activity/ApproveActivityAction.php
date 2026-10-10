<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\User;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;
use App\Support\TaskSettlement;
use Illuminate\Database\ConnectionInterface;

/**
 * Pemberi kerja menyetujui hasil SATU MITRA → upahnya langsung masuk saldo.
 *
 * Yang dikreditkan `activities.agreed_amount` milik mitra itu (bukan
 * `tasks.agreed_amount`, yang adalah TOTAL seluruh pekerja). Dibayar saat
 * mitra itu disetujui, tidak menunggu mitra terakhir — sejak sengketa per
 * mitra, menunggu berarti mitra yang kerjanya baik ikut tertahan oleh
 * sengketa orang lain. Task ditutup lewat [TaskSettlement::sync] begitu
 * semua mitra diputuskan; aturannya ada di sana.
 *
 * Pengaman pembayaran ganda: unique (reference_type, reference_id, type) di
 * `wallet_entries` — satu activity paling banyak satu baris `earning`.
 *
 * SEMENTARA: selama gerbang pembayaran dimatikan
 * (`config/sekarya.payments.gate_enabled`), tidak ada dana yang ditahan —
 * pekerjaannya tetap ditutup, yang tertunda uangnya.
 */
final class ApproveActivityAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskSettlement $settlement,
        private readonly PushDispatcher $push,
    ) {}

    /**
     * @param  ActorType  $actorType  siapa yang tercatat menyetujui di riwayat
     *                                status. Tombol poster memakai bawaan (`poster`); persetujuan otomatis
     *                                sistem meneruskan `system` supaya audit tetap jujur.
     */
    public function handle(
        Activity $activity,
        User $poster,
        ?string $note = null,
        ActorType $actorType = ActorType::Poster,
        ?int $actorId = null,
    ): Activity {
        $activity = $this->db->transaction(function () use ($activity, $poster, $note, $actorType, $actorId): Activity {
            $task = $this->settlement->lockTask($activity->task);
            $activity = Activity::query()->whereKey($activity->getKey())->lockForUpdate()->firstOrFail();

            // Hanya hasil yang MENUNGGU penilaian. Activity yang disengketakan
            // (`rejected`) keluar lewat keputusan pengelola, bukan tombol ini.
            if ($activity->status !== ActivityStatus::Submitted) {
                throw InvalidStatusTransitionException::between(
                    $activity->status->value,
                    ActivityStatus::Approved->value,
                );
            }

            $activity->forceFill(['poster_note' => $note]);
            $this->settlement->approve($activity);
            $this->settlement->sync(
                $task,
                $actorType,
                $actorId ?? (int) $poster->getKey(),
                'hasil disetujui',
            );

            return $activity;
        });

        // Di LUAR transaksi: pekerja diberi tahu hasilnya disetujui.
        $this->push->send(
            $activity->worker_id,
            PushMessages::activityApproved($activity->task, $activity),
        );

        return $activity;
    }
}
