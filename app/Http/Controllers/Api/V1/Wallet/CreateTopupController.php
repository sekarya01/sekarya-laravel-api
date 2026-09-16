<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Wallet\RequestTopupAction;
use App\Data\Wallet\CreateTopupData;
use App\Http\Requests\Api\V1\Wallet\CreateTopupRequest;
use App\Http\Resources\Api\V1\WalletTopupResource;
use Illuminate\Http\JsonResponse;

/**
 * Melapor sudah transfer untuk mengisi saldo. TIDAK menambah saldo.
 *
 * `201`: sebuah permintaan memang dibuat. Yang belum terjadi adalah uangnya
 * masuk, dan itu terbaca dari `status` serta `awaits_confirmation` di
 * badannya — bukan dari status code.
 */
final class CreateTopupController
{
    public function __construct(private readonly RequestTopupAction $action) {}

    public function __invoke(CreateTopupRequest $request): JsonResponse
    {
        $topup = $this->action->handle($request->user(), CreateTopupData::fromRequest($request));

        return WalletTopupResource::make($topup)
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }
}
