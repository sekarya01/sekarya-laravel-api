<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jawaban satu pekerja atas permintaan pembatalan.
 *
 * `Pending` dibuat di muka untuk setiap pekerja saat permintaannya lahir,
 * bukan menunggu orangnya menjawab: dengan begitu "siapa yang belum
 * menjawab" terbaca dari tabel, bukan dari selisih antara daftar pekerja dan
 * daftar jawaban yang harus dihitung ulang di setiap tempat yang bertanya.
 */
enum CancelApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
