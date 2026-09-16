<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Http\Resources\Api\V1\WalletResource;
use Illuminate\Http\Request;

/**
 * Saldo sendiri.
 *
 * `walletOrNew()`, bukan `walletFor()`: membaca saldo tidak boleh membuat
 * baris. Kalau GET ini menulis, setiap orang yang sekadar membuka layar saldo
 * meninggalkan baris dompet kosong — dan pertanyaan "siapa yang benar-benar
 * pernah memakai saldo" berhenti bisa dijawab dari tabelnya.
 */
final class ShowWalletController
{
    public function __invoke(Request $request): WalletResource
    {
        return WalletResource::make($request->user()->walletOrNew());
    }
}
