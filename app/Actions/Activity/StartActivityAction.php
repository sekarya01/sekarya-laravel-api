<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\PaymentNotHeldException;
use App\Jobs\SendPushNotification;
use App\Models\Activity;
use App\Support\Push\PushMessages;
use Illuminate\Database\ConnectionInterface;

final class StartActivityAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(Activity $activity): Activity
    {
        $activity = $this->db->transaction(function () use ($activity): Activity {
            // INILAH yang dijaga uang: mulai bekerja, bukan keberadaan
            // activity-nya. Activity lahir saat deal — ia catatan siapa
            // mengerjakan apa; yang tidak boleh terjadi tanpa dana adalah
            // orang menghabiskan waktunya.
            //
            // Diperiksa di sini, bukan sekali saat barisnya dibuat: dana bisa
            // sudah dikembalikan sejak deal.
            //
            // Dilewati selama gerbang pembayaran dimatikan — mekanisme
            // pembayarannya belum ada, jadi tagihan tidak pernah beranjak dari
            // `pending` dan menuntut `held` berarti tidak ada pekerjaan yang
            // pernah bisa dimulai. Lihat config/sekarya.payments.
            if ((bool) config('sekarya.payments.gate_enabled')
                && ! $activity->payment->status->opensActivity()) {
                throw PaymentNotHeldException::becauseStatus($activity->payment->status);
            }

            // `arrived → in_progress` satu-satunya jalan masuk: pekerjaan
            // tidak dimulai dari perjalanan, dan kedatangan yang belum diakui
            // pemberi kerja belum jadi kedatangan.
            if (! $activity->status->canTransitionTo(ActivityStatus::InProgress)) {
                throw InvalidStatusTransitionException::between(
                    $activity->status->value,
                    ActivityStatus::InProgress->value,
                );
            }

            $activity->forceFill([
                'status' => ActivityStatus::InProgress,
                'started_at' => now(),
            ])->save();

            return $activity;
        });

        // Di LUAR transaksi: pemberi kerja diberi tahu pekerjaan dimulai.
        $task = $activity->task;
        SendPushNotification::dispatch(
            $task->poster_id,
            PushMessages::activityInProgress($task, $activity),
        );

        return $activity;
    }
}
