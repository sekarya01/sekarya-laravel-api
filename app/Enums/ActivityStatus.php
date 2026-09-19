<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Perjalanan satu pekerjaan, dari diterima sampai disetujui.
 *
 * Dua langkah di depan `in_progress` sengaja dipisah, dan pemiliknya berbeda:
 *
 *  - `on_the_way` DIUMUMKAN PEKERJA. Ia berangkat; belum ada yang bisa
 *    dibuktikan selain niatnya.
 *  - `arrived` DIKONFIRMASI PEMBERI KERJA. Yang melihat orangnya sampai di
 *    depan pintu adalah tuan rumah, bukan orang yang datang. Kalau pekerja
 *    boleh menyatakan sendiri ia sudah tiba, "sudah sampai" berhenti berarti
 *    apa pun — dan pemberi kerja tidak punya satu titik pun untuk menyanggah.
 *
 * Karena itu pekerjaan tidak bisa langsung `open → in_progress`: mulai
 * bekerja menuntut kedatangan yang sudah diakui kedua belah pihak.
 */
enum ActivityStatus: string
{
    case Open = 'open';

    /** Pekerja menyatakan berangkat ke lokasi. */
    case OnTheWay = 'on_the_way';

    /** Pemberi kerja mengonfirmasi pekerjanya sudah sampai. */
    case Arrived = 'arrived';

    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Open => [self::OnTheWay],
            self::OnTheWay => [self::Arrived],
            self::Arrived => [self::InProgress],
            self::InProgress => [self::Submitted],
            self::Submitted => [self::Approved, self::Rejected],
            self::Rejected => [self::Submitted],
            self::Approved => [],
        }, true);
    }

    /** Pekerjaannya sudah benar-benar berjalan di tangan pekerja. */
    public function isUnderway(): bool
    {
        return $this === self::InProgress || $this === self::Submitted;
    }
}
