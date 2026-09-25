<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Wallet\ShowWalletConfigAction;
use App\Http\Resources\Api\V1\WalletConfigResource;

/**
 * Rekening tujuan isi saldo + batas nominal (B1). Tidak butuh pemilik: isinya
 * sama untuk semua pengguna yang login.
 */
final class ShowWalletConfigController
{
    public function __construct(private readonly ShowWalletConfigAction $action) {}

    public function __invoke(): WalletConfigResource
    {
        return WalletConfigResource::make($this->action->handle());
    }
}
