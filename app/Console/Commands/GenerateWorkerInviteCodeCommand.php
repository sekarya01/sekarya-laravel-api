<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\WorkerInvite\CreateWorkerInviteCodeAction;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Terbitkan kode undangan mitra dari terminal. Plain-nya dicetak SEKALI —
 * tidak tersimpan, tidak bisa ditampilkan lagi.
 */
class GenerateWorkerInviteCodeCommand extends Command
{
    protected $signature = 'sekarya:worker-code {--max-uses=1 : Berapa kali kode boleh dipakai} {--expires= : Tanggal kedaluwarsa (Y-m-d H:i)} {--note= : Catatan admin}';
    protected $description = 'Terbitkan kode undangan pendaftaran mitra pekerja (8 char, disimpan sebagai hash)';

    public function handle(CreateWorkerInviteCodeAction $action): int
    {
        $expires = $this->option('expires') !== null
            ? Carbon::parse((string) $this->option('expires'))
            : null;

        $result = $action->handle(
            (int) $this->option('max-uses'),
            $expires,
            $this->option('note') !== null ? (string) $this->option('note') : null,
            null,
        );

        $this->line('Kode: <info>'.$result['plain'].'</info>');
        $this->line('Maks pakai: '.$result['code']->max_uses);
        $this->line('Kedaluwarsa: '.($result['code']->expires_at?->toDateTimeString() ?? '-'));

        return self::SUCCESS;
    }
}
