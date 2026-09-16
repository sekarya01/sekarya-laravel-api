<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Wallet;

use App\Actions\Admin\Wallet\CompleteWithdrawalAction;
use App\Http\Requests\Api\V1\Admin\CompleteWithdrawalRequest;
use App\Http\Resources\Api\V1\Admin\AdminWalletWithdrawalResource;
use App\Models\WalletWithdrawal;

/** Transfer ke rekening sudah dikirim. TIDAK menyentuh saldo — sudah ditahan. */
final class CompleteWithdrawalController
{
    public function __construct(private readonly CompleteWithdrawalAction $action) {}

    public function __invoke(
        CompleteWithdrawalRequest $request,
        WalletWithdrawal $withdrawal,
    ): AdminWalletWithdrawalResource {
        $reference = trim((string) $request->input('transfer_reference'));

        return AdminWalletWithdrawalResource::make($this->action->handle(
            $withdrawal,
            $request->user(),
            $reference === '' ? null : $reference,
            $request->ip(),
        )->load(['user', 'verification']));
    }
}
