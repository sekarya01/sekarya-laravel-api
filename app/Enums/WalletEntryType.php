<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sebab sebuah baris buku besar ada.
 *
 * Arahnya DITURUNKAN dari jenisnya, tidak diminta dari pemanggil. Kalau
 * pemanggil yang menentukan, cepat atau lambat ada satu tempat yang menulis
 * `earning` sebagai debit — dan yang menanggungnya pekerja yang saldonya
 * berkurang saat dibayar, tanpa satu baris kode pun yang terlihat salah.
 *
 * Nilainya bagian dari kontrak: begitu tercatat di basis data ia tidak bisa
 * diganti nama tanpa membuat riwayat lama tak terbaca.
 */
enum WalletEntryType: string
{
    /** Pengguna mengisi saldo; dikonfirmasi pengelola. */
    case Topup = 'topup';

    /** Dana task yang batal dikembalikan ke pemberi kerja. */
    case Refund = 'refund';

    /** Upah pekerja saat dana task dilepas. */
    case Earning = 'earning';

    /** Saldo ditahan saat penarikan diminta — bukan saat dicairkan. */
    case Withdrawal = 'withdrawal';

    /** Penarikan ditolak atau dibatalkan; tahanannya dikembalikan. */
    case WithdrawalReversal = 'withdrawal_reversal';

    /** Koreksi manual dari sisi pengelola. Tidak punya endpoint. */
    case AdjustmentCredit = 'adjustment_credit';

    case AdjustmentDebit = 'adjustment_debit';

    public function direction(): WalletEntryDirection
    {
        return match ($this) {
            self::Topup,
            self::Refund,
            self::Earning,
            self::WithdrawalReversal,
            self::AdjustmentCredit => WalletEntryDirection::Credit,

            self::Withdrawal,
            self::AdjustmentDebit => WalletEntryDirection::Debit,
        };
    }

    /** Uang yang datang dari pekerjaan, bukan dari isi ulang sendiri. */
    public function isEarning(): bool
    {
        return $this === self::Earning;
    }
}
