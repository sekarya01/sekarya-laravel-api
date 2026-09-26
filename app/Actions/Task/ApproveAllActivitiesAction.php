<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Actions\Activity\ApproveActivityAction;
use App\Enums\ActivityStatus;
use App\Exceptions\Domain\NoSubmittedActivitiesException;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * "Konfirmasi Selesai & Rilis Dana" (B16): setujui SEMUA hasil yang sudah
 * diserahkan pada satu task dengan sekali tekan.
 *
 * Klien bisa saja memanggil `POST activities/{a}/approve` berulang, tapi
 * tombolnya satu dan tidak boleh meninggalkan persetujuan SEBAGIAN kalau
 * salah satu gagal di tengah — karena itu seluruhnya satu transaksi.
 * `ApproveActivityAction` tetap satu-satunya tempat aturan "dana dilepas saat
 * pekerja terakhir disetujui" ditulis; Action ini hanya mengulanginya.
 */
final class ApproveAllActivitiesAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ApproveActivityAction $approve,
    ) {}

    public function handle(Task $task, User $poster): Task
    {
        return $this->db->transaction(function () use ($task, $poster): Task {
            $submitted = $task->activities()
                ->where('status', ActivityStatus::Submitted->value)
                ->orderBy('id')
                ->get();

            if ($submitted->isEmpty()) {
                throw NoSubmittedActivitiesException::make();
            }

            foreach ($submitted as $activity) {
                $this->approve->handle($activity, $poster);
            }

            return $task->refresh();
        });
    }
}
