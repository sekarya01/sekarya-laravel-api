<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\WorkerInvite\CreateWorkerInviteCodeAction;
use App\Data\Admin\CreateWorkerInviteCodeData;
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
        $result = $action->handle(CreateWorkerInviteCodeData::fromValues([
            'max_uses' => $this->option('max-uses'),
            'expires_at' => $this->option('expires'),
            'note' => $this->option('note'),
            'city' => $this->option('city'),
            'province' => $this->option('province'),
        ]), null);

        $this->line('Kode: <info>'.$result['plain'].'</info>');
        $this->line('Maks pakai: '.$result['code']->max_uses);
        $this->line('Kedaluwarsa: '.($result['code']->expires_at?->toDateTimeString() ?? '-'));
        $this->line('Wilayah: '.$result['code']->areaLabel());

        return self::SUCCESS;
    }
}
