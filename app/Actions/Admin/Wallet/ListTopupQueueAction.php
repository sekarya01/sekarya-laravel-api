<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Data\Admin\WalletQueueData;
use App\Enums\WalletTopupStatus;
use App\Models\WalletTopup;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Antrean isi saldo. Paling lama menunggu di depan.
 *
 * Bawaannya `awaiting_confirmation` — satu-satunya status yang menunggu
 * tindakan manusia, sama seperti antrean pembayaran dan antrean verifikasi.
 */
final class ListTopupQueueAction
{
    /** @return CursorPaginator<int, WalletTopup> */
    public function handle(WalletQueueData $data): CursorPaginator
    {
        $status = $data->status === null
            ? WalletTopupStatus::AwaitingConfirmation
            : WalletTopupStatus::from($data->status);

        return WalletTopup::query()
            ->where('status', $status)
            ->with('user')
            ->queueOrder()
            ->cursorPaginate($data->page->perPage);
    }
}
