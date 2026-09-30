<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tutup lelang yang batas waktunya sudah lewat (G12). Idempoten: hanya
// menyentuh task `open` yang `bidding_closes_at`-nya <= sekarang.
Schedule::command('sekarya:tasks:expire-bidding')->everyFiveMinutes();

// Chat: room `expired` yang melewati masa simpan dihapus isinya permanen.
// Harian, di luar jam ramai. Idempoten.
Schedule::command('sekarya:chat:purge-expired')->dailyAt('03:30');
