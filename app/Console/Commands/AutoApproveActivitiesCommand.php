<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Activity\AutoApproveStaleActivitiesAction;
use Illuminate\Console\Command;

/**
 * Setujui otomatis hasil yang tak kunjung dikonfirmasi (batas dari
 * `sekarya.activities.auto_approve_hours`). Dijadwalkan per jam di
 * `routes/console.php`.
 */
final class AutoApproveActivitiesCommand extends Command
{
    protected $signature = 'sekarya:activities:auto-approve';

    protected $description = 'Setujui hasil `submitted` yang melewati tenggang konfirmasi agar pekerja dibayar';

    public function handle(AutoApproveStaleActivitiesAction $action): int
    {
        $approved = $action->handle();

        $this->info("Hasil disetujui otomatis: {$approved} activity.");

        return self::SUCCESS;
    }
}
