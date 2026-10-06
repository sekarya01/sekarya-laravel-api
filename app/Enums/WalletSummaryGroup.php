<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Satuan deret pada `GET me/wallet/summary?group=…`.
 *
 * `day`/`week` dibuat untuk grafik layar Pemasukan: deretnya diisi nol
 * (setiap hari/pekan dalam rentang muncul), sehingga klien tidak perlu
 * menambal celah sendiri. `month` mempertahankan perilaku lamanya — hanya
 * bulan yang punya baris.
 */
enum WalletSummaryGroup: string
{
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';

}
