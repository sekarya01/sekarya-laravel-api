<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Arah sebuah baris buku besar. Dua nilai, dan hanya dua.
 *
 * Disimpan sebagai kolom sendiri, bukan diturunkan dari tanda `amount`:
 * `amount` di seluruh aplikasi ini adalah bilangan bulat TAK BERTANDA dalam
 * satuan terkecil (lihat `payments.amount`, `bids.amount`). Menyimpan debit
 * sebagai angka negatif berarti satu kolom di satu tabel memakai konvensi yang
 * berbeda dari semua kolom uang lainnya — dan penjumlahan yang lupa itu tidak
 * menimbulkan galat, ia cuma menghasilkan saldo yang salah.
 */
enum WalletEntryDirection: string
{
    /** Saldo bertambah. */
    case Credit = 'credit';

    /** Saldo berkurang. */
    case Debit = 'debit';

    /** Tanda yang dipakai saat menjumlahkan buku besar. */
    public function sign(): int
    {
        return $this === self::Credit ? 1 : -1;
    }
}
