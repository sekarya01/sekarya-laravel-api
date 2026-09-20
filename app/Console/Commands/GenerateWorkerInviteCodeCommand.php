<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\WorkerInvite\CreateWorkerInviteCodeAction;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Terbitkan kode undangan mitra dari terminal. Kodenya tampil terus di
 * menu Kode Mitra (tersimpan plain di barisnya) — yang dicetak di sini
 * untuk langsung disalin.
 */
class GenerateWorkerInviteCodeCommand extends Command
{
    protected $signature = 'sekarya:worker-code {--max-uses=1 : Berapa kali kode boleh dipakai} {--expires= : Tanggal kedaluwarsa (Y-m-d H:i)} {--note= : Catatan admin} {--city= : Kota cakupan (kosong = nasional)} {--province= : Provinsi cakupan (kosong = nasional)}';
    protected $description = 'Terbitkan kode undangan pendaftaran mitra pekerja (8 char, hash + plain tersimpan)';

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
            null,
            $this->option('city') !== null ? (string) $this->option('city') : null,
            $this->option('province') !== null ? (string) $this->option('province') : null,
        );

        $this->line('Kode: <info>'.$result['plain'].'</info>');
        $this->line('Maks pakai: '.$result['code']->max_uses);
        $this->line('Kedaluwarsa: '.($result['code']->expires_at?->toDateTimeString() ?? '-'));
        $this->line('Wilayah: '.$result['code']->areaLabel());

        return self::SUCCESS;
    }
}
