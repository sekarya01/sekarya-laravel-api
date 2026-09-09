<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Exceptions\Domain\NoWorkersHiredException;
use App\Exceptions\Domain\TaskNotBiddableException;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskHiring;
use Illuminate\Database\ConnectionInterface;

/**
 * Mulai dengan pekerja yang sudah ada, tanpa menunggu slot penuh.
 *
 * Sebuah task 30 orang yang hanya mendapat 18 pelamar layak tidak boleh
 * tersandera oleh angka 30 — pekerjaan bertanggal tetap harus jalan. Aksi ini
 * MENURUNKAN `workers_needed` ke jumlah yang sudah diterima, lalu menutup
 * lelang seperti biasa.
 *
 * Targetnya diturunkan, bukan sekadar "dipaksa deal", supaya `workers_needed`
 * tetap menjawab pertanyaan yang sama sesudahnya: berapa orang yang mengerjakan
 * task ini. Kalau dibiarkan di 30, setiap laporan akan menampilkan task ini
 * sebagai kekurangan 12 orang selamanya.
 */
final class StartWithCurrentWorkersAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskHiring $hiring,
    ) {}

    public function handle(Task $task, User $poster): Task
    {
        return $this->db->transaction(function () use ($task, $poster): Task {
            $task = Task::query()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();

            if (! $task->status->acceptsBids()) {
                throw TaskNotBiddableException::becauseStatus($task->status);
            }

            // Hitung ulang dulu: penghitung boleh saja tertinggal kalau ada
            // penawaran yang berubah lewat jalur lain, dan angka inilah yang
            // dikunci menjadi target permanen.
            $this->hiring->sync($task);

            if ($task->workers_hired < 1) {
                throw NoWorkersHiredException::make();
            }

            $task->forceFill(['workers_needed' => $task->workers_hired])->save();

            $this->hiring->close($task, $poster, sprintf(
                'dimulai lebih awal dengan %d pekerja',
                $task->workers_hired,
            ));

            return $task->refresh();
        });
    }
}
