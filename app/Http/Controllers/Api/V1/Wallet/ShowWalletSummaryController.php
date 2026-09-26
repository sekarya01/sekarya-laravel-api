<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Wallet\SummarizeWalletAction;
use App\Data\Wallet\WalletSummaryQueryData;
use App\Http\Requests\Api\V1\Wallet\ShowWalletSummaryRequest;
use App\Http\Resources\Api\V1\WalletSummaryResource;

/**
 * Total masuk/keluar untuk layar Riwayat dan pendapatan mingguan untuk
 * Beranda Mitra. Selalu milik yang sedang login — tidak ada parameter
 * pemilik, jadi tidak ada ringkasan orang lain yang bisa diminta.
 */
final class ShowWalletSummaryController
{
    public function __construct(private readonly SummarizeWalletAction $action) {}

    public function __invoke(ShowWalletSummaryRequest $request): WalletSummaryResource
    {
        return WalletSummaryResource::make(
            $this->action->handle($request->user(), WalletSummaryQueryData::fromRequest($request)),
        );
    }
}
