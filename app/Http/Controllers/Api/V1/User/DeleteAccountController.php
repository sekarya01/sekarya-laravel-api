<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\DeleteAccountAction;
use App\Data\User\DeleteAccountData;
use App\Http\Requests\Api\V1\User\DeleteAccountRequest;
use Illuminate\Http\Response;

/** Hapus akun (G3). Balasan kosong; akun tak bisa login lagi. */
final class DeleteAccountController
{
    public function __construct(private readonly DeleteAccountAction $action) {}

    public function __invoke(DeleteAccountRequest $request): Response
    {
        $this->action->handle(DeleteAccountData::fromRequest($request), $request->user());

        return response()->noContent();
    }
}
