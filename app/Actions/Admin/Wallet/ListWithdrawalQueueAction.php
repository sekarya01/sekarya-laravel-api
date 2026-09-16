<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Data\Admin\WalletQueueData;
use App\Enums\WalletWithdrawalStatus;
use App\Models\WalletWithdrawal;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Antrean pencairan. Paling lama menunggu di depan.
 *
 * `verification` ikut dimuat supaya daftarnya bisa menyebut bank dan nama
 * pemilik rekening tanpa satu kueri per baris. Nomor rekeningnya TIDAK ikut
 * keluar dari sini: ia hanya terbaca di `GET /admin/verifications/{id}`, dan
 * pembacaan di sana dicatat. Daftar ini sengaja tidak punya kode untuk
 * mengeluarkannya — pola yang sama dengan antrean verifikasi.
 */
final class ListWithdrawalQueueAction
{
    /** @return CursorPaginator<int, WalletWithdrawal> */
    public function handle(WalletQueueData $data): CursorPaginator
    {
        $status = $data->status === null
            ? WalletWithdrawalStatus::Requested
            : WalletWithdrawalStatus::from($data->status);

        return WalletWithdrawal::query()
            ->where('status', $status)
            ->with(['user', 'verification'])
            ->queueOrder()
            ->cursorPaginate($data->page->perPage);
    }
}
