<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Task\ExpireBiddingAction;
use Illuminate\Console\Command;

/**
 * Tutup lelang yang batas waktunya sudah lewat (G12). Dijadwalkan tiap lima
 * menit di `routes/console.php`.
 */
final class ExpireBiddingCommand extends Command
{
    protected $signature = 'sekarya:tasks:expire-bidding';

    protected $description = 'Ubah task `open` yang `bidding_closes_at`-nya lewat menjadi `expired`';

    public function handle(ExpireBiddingAction $action): int
    {
        $closed = $action->handle();

        $this->info("Lelang ditutup: {$closed} task.");

        return self::SUCCESS;
    }
}
