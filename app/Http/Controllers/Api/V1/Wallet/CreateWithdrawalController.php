<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Wallet\RequestWithdrawalAction;
use App\Data\Wallet\CreateWithdrawalData;
use App\Http\Requests\Api\V1\Wallet\CreateWithdrawalRequest;
use App\Http\Resources\Api\V1\WalletWithdrawalResource;
use Illuminate\Http\JsonResponse;

/** Minta pencairan saldo. Saldonya langsung berkurang — lihat Action-nya. */
final class CreateWithdrawalController
{
    public function __construct(private readonly RequestWithdrawalAction $action) {}

    public function __invoke(CreateWithdrawalRequest $request): JsonResponse
    {
        $withdrawal = $this->action->handle(
            $request->user(),
            CreateWithdrawalData::fromRequest($request),
        );

        return WalletWithdrawalResource::make($withdrawal->load('verification'))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
