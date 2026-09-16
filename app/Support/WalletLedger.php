<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Exceptions\Domain\InsufficientBalanceException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * SATU-SATUNYA tempat saldo berubah.
 *
 * Bukan kenyamanan — ia penjaga tiga invarian yang tidak bisa ditegakkan dari
 * Action masing-masing:
 *
 *  1. **Setiap perubahan saldo meninggalkan baris buku besar.** Saldo yang
 *     bergeser tanpa baris penjelas adalah selisih yang tidak bisa ditelusuri
 *     siapa pun, dan pada uang itu berakhir sebagai kata-kata pengguna melawan
 *     kata-kata kita.
 *
 *  2. **Baris dompet dikunci lebih dulu, selalu.** InnoDB menerjemahkan
 *     `lockForUpdate()` jadi `SELECT ... FOR UPDATE`, jadi baca-lalu-tulis di
 *     sini benar-benar berurutan. Tanpa itu dua kredit bersamaan sama-sama
 *     membaca saldo lama dan yang belakangan menimpa yang duluan — uangnya
 *     hilang, dan tidak ada galat apa pun.
 *
 *  3. **Debit tidak pernah membuat saldo negatif.** Diperiksa DI DALAM kunci
 *     yang sama dengan penulisannya. Pemeriksaan yang dilakukan pemanggil
 *     sebelum memanggil ini cuma pemeriksaan pada angka basi.
 *
 * Pemanggil WAJIB sudah berada di dalam transaksi kalau perubahan saldo harus
 * gagal bersama pekerjaan lain di Action-nya. Kelas ini tidak membuka
 * transaksi sendiri justru karena itu: transaksi bersarang di Laravel adalah
 * savepoint, dan `commit` di dalamnya akan menyatakan selesai sesuatu yang
 * masih bisa dibatalkan di luar.
 */
final class WalletLedger
{
    private const int DESCRIPTION_LIMIT = 200;

    /**
     * Dompet milik seseorang, dibuat kalau belum ada. Jalur TULIS.
     *
     * Jalur BACA memakai `User::walletOrNew()` — sebuah GET yang membuat baris
     * berarti sekadar membuka layar saldo sudah menambah baris di basis data.
     */
    public function walletFor(User $user): Wallet
    {
        return Wallet::query()->firstOrCreate(['user_id' => $user->getKey()]);
    }

    /** Saldo bertambah. */
    public function credit(
        Wallet $wallet,
        WalletEntryType $type,
        int $amount,
        ?Model $reference = null,
        ?string $description = null,
    ): WalletEntry {
        // Arah datang dari JENISNYA, jadi `credit()` dan `debit()` sebenarnya
        // mengerjakan hal yang sama. Keduanya tetap ada supaya tempat
        // pemanggilan menyebut arahnya — dan penjaga ini yang membuat
        // `credit($wallet, Withdrawal, ...)` gagal saat itu juga, alih-alih
        // mengurangi saldo dari baris kode yang terbaca seperti menambah.
        $this->assertDirection($type, WalletEntryDirection::Credit);

        return $this->record($wallet, $type, $amount, $reference, $description);
    }

    /**
     * Saldo berkurang.
     *
     * @throws InsufficientBalanceException kalau saldo tidak mencukupi
     */
    public function debit(
        Wallet $wallet,
        WalletEntryType $type,
        int $amount,
        ?Model $reference = null,
        ?string $description = null,
    ): WalletEntry {
        $this->assertDirection($type, WalletEntryDirection::Debit);

        return $this->record($wallet, $type, $amount, $reference, $description);
    }

    private function assertDirection(WalletEntryType $type, WalletEntryDirection $expected): void
    {
        if ($type->direction() !== $expected) {
            throw new RuntimeException(sprintf(
                'Jenis mutasi "%s" berarah %s dan tidak bisa dicatat sebagai %s.',
                $type->value,
                $type->direction()->value,
                $expected->value,
            ));
        }
    }

    private function record(
        Wallet $wallet,
        WalletEntryType $type,
        int $amount,
        ?Model $reference,
        ?string $description,
    ): WalletEntry {
        // Nol dan negatif ditolak sebagai galat pemrogram, bukan sebagai
        // pelanggaran aturan bisnis: keduanya hanya bisa datang dari kode yang
        // salah menghitung, tidak pernah dari pengguna. Baris bernilai nol
        // juga mengotori riwayat dengan kejadian yang tidak mengubah apa pun.
        if ($amount <= 0) {
            throw new RuntimeException('Jumlah mutasi saldo harus lebih besar dari nol.');
        }

        $direction = $type->direction();

        // Kunci baris dompetnya. Dibaca ulang dari basis data — instance yang
        // dioper pemanggil bisa saja sudah basi, dan saldo yang dihitung dari
        // angka basi adalah persis kesalahan yang dicegah kunci ini.
        $locked = Wallet::query()
            ->whereKey($wallet->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $balance = (int) $locked->balance + ($direction->sign() * $amount);

        if ($balance < 0) {
            throw InsufficientBalanceException::forAmount((int) $locked->balance, $amount);
        }

        $entry = new WalletEntry;
        $entry->forceFill([
            'wallet_id' => $locked->getKey(),
            'type' => $type,
            'direction' => $direction,
            'amount' => $amount,
            'balance_after' => $balance,
            // Slug tabel, bukan nama kelas PHP — nama kelas berubah saat
            // direfaktor dan riwayat lama jadi tak cocok dengan yang baru.
            'reference_type' => $reference === null ? null : $reference->getTable(),
            'reference_id' => $reference?->getKey(),
            'description' => $this->trim($description),
            'created_at' => now(),
        ])->save();

        // Cache-nya ditulis dari angka yang baru saja dihitung di dalam kunci,
        // bukan dengan increment terpisah: dua pernyataan berarti dua peluang
        // untuk berbeda dari `balance_after` barisnya.
        $locked->forceFill(['balance' => $balance])->save();

        // Instance milik pemanggil ikut disegarkan. Kalau tidak, Action yang
        // menampilkan dompetnya sesudah ini akan mengembalikan saldo sebelum
        // mutasi — respons yang salah atas permintaan yang berhasil.
        $wallet->forceFill(['balance' => $balance])->syncOriginal();

        return $entry;
    }

    private function trim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : mb_substr($value, 0, self::DESCRIPTION_LIMIT);
    }
}
