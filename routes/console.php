<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Ketiga jadwal dijalankan DI DALAM proses `schedule:run` (`Schedule::call` +
 * `Artisan::call`), bukan sebagai proses anak (`Schedule::command`).
 *
 * Di hosting produksi (cPanel), proses anak yang dibuat `schedule:run` selesai
 * dalam ±14 ms dengan status DONE tanpa memproses apa pun — keluarannya dibuang
 * ke /dev/null sehingga penyebabnya tak terlihat — sementara command yang sama
 * yang dijalankan cron langsung menutup 16 task (2026-10-09). Di dalam proses
 * yang sama tidak ada proses anak yang bisa gagal diam-diam: keluaran command
 * ikut tertulis ke log cron, dan exception ditandai FAIL + dilaporkan.
 */
$inProcess = static function (string $command): Closure {
    return static function () use ($command): void {
        Artisan::call($command);
        echo Artisan::output();
    };
};

// Tutup lelang yang batas waktunya sudah lewat (G12). Idempoten: hanya
// menyentuh task `open` yang `bidding_closes_at`-nya <= sekarang, atau yang
// `needed_at`-nya lewat tanpa pekerja diterima (`workers_hired` = 0).
Schedule::call($inProcess('sekarya:tasks:expire-bidding'))
    ->name('sekarya:tasks:expire-bidding')
    ->everyFiveMinutes();

// Chat: room `expired` yang melewati masa simpan dihapus isinya permanen.
// Harian, di luar jam ramai. Idempoten.
Schedule::call($inProcess('sekarya:chat:purge-expired'))
    ->name('sekarya:chat:purge-expired')
    ->dailyAt('03:30');

// Hasil yang tak kunjung dikonfirmasi disetujui otomatis agar pekerja
// dibayar. Idempoten: hanya menyentuh activity `submitted` yang
// `submitted_at`-nya melewati tenggang (`sekarya.activities.auto_approve_hours`).
Schedule::call($inProcess('sekarya:activities:auto-approve'))
    ->name('sekarya:activities:auto-approve')
    ->hourly();
