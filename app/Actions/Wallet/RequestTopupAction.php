<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Wallet\CreateTopupData;
use App\Enums\WalletTopupStatus;
use App\Exceptions\Domain\TooManyPendingWalletRequestsException;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Pengguna menyatakan sudah mentransfer sejumlah uang untuk mengisi saldo.
 *
 * TIDAK menambah saldo apa pun. Pola yang sama persis dengan laporan transfer
 * task: yang menyatakan uangnya benar-benar masuk adalah orang yang melihat
 * mutasi rekening, bukan yang mengaku sudah mengirim. Kalau panggilan ini
 * menambah saldo, siapa pun bisa mengisi dompetnya sendiri dengan satu
 * permintaan HTTP — dan saldo itu bisa langsung ditarik ke rekening.
 */
final class RequestTopupAction
{
    private const int CODE_ATTEMPTS = 5;

    public function __construct(private readonly ConnectionInterface $db) {}

    /** [#impure] [#io: db] [#tx: own] [#lock: wallet_topups] [#warn: nominal unik dijaga index, bukan kunci — coba ulang] */
    public function handle(User $user, CreateTopupData $data): WalletTopup
    {
        return $this->db->transaction(function () use ($user, $data): WalletTopup {
            $pending = WalletTopup::query()
                ->where('user_id', $user->getKey())
                ->where('status', WalletTopupStatus::AwaitingConfirmation)
                ->lockForUpdate()
                ->count();

            $max = (int) config('sekarya.wallet.max_pending_requests');

            if ($pending >= $max) {
                throw TooManyPendingWalletRequestsException::topups($pending, $max);
            }

            $topup = new WalletTopup;
            $topup->fill([
                'user_id' => $user->getKey(),
                'amount' => $data->amount,
                'sender_note' => $data->senderNote,
            ]);

            // Kode dibaca tanpa kunci lintas pengguna, jadi dua permintaan
            // bersamaan bisa memilih yang sama; unique `awaiting_transfer_lock`
            // menolak yang kedua, dan ia memilih ulang. Kode yang sudah dicoba
            // dikecualikan eksplisit: di REPEATABLE READ, baris milik transaksi
            // yang baru commit tidak terlihat oleh pembacaan biasa.
            $tried = [];
            for ($attempt = 1; ; $attempt++) {
                $uniqueCode = $this->uniqueCode($tried);
                $tried[] = $uniqueCode;

                // `status`, `unique_code`, dan `transfer_amount` tidak
                // mass-assignable — disetel di sini, eksplisit, supaya nilainya
                // tidak bergantung pada bawaan kolom yang bisa berubah tanpa ada
                // yang membaca ulang Action ini. Nominal transfer = jumlah + kode,
                // supaya pengelola bisa mencocokkan mutasinya PERSIS.
                $topup->forceFill([
                    'status' => WalletTopupStatus::AwaitingConfirmation,
                    'unique_code' => $uniqueCode,
                    'transfer_amount' => $data->amount + $uniqueCode,
                ]);

                try {
                    $topup->save();

                    return $topup;
                } catch (UniqueConstraintViolationException $e) {
                    if ($attempt >= self::CODE_ATTEMPTS || ! str_contains($e->getMessage(), 'awaiting_transfer_lock')) {
                        throw $e;
                    }
                }
            }
        });
    }

    /**
     * Kode 3 digit yang belum dipakai permintaan yang masih menunggu.
     *
     * Dibatasi 1..999 (nol bukan kode yang terlihat di mutasi). Kalau seluruh
     * rentang terpakai — praktis mustahil dengan batas antrean — jatuh ke kode
     * acak; nominal persisnya yang tetap menjadi kunci pencocokan.
     */
    /** @param list<int> $exclude */
    private function uniqueCode(array $exclude = []): int
    {
        $taken = WalletTopup::query()
            ->where('status', WalletTopupStatus::AwaitingConfirmation)
            ->pluck('unique_code')
            ->map(static fn (mixed $code): int => (int) $code)
            ->merge($exclude)
            ->all();

        $free = array_values(array_diff(range(1, 999), $taken));

        return $free === [] ? random_int(1, 999) : $free[array_rand($free)];
    }
}
