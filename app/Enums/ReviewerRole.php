<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewerRole: string
{
    case Poster = 'poster';
    case Worker = 'worker';

    /** Penilaian dari poster menaikkan agregat worker, dan sebaliknya. */
    public function affectedAggregate(): string
    {
        return match ($this) {
            self::Poster => 'worker',
            self::Worker => 'poster',
        };
    }

    /**
     * Agregat yang terpengaruh tinggal di tabel mana.
     *
     * Reputasi sebagai PEKERJA pindah ke `user_workers`; reputasi sebagai
     * PEMBERI KERJA tetap di `users`, karena pemberi kerja tidak punya profil
     * terpisah. Keputusannya di sini, bukan sebagai perbandingan string di
     * dalam Action — kalau nanti ada peran ketiga, hanya berkas ini yang
     * perlu menjawabnya.
     */
    public function affectsWorkerProfile(): bool
    {
        return $this->affectedAggregate() === 'worker';
    }
}
