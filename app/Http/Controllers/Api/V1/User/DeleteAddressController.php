<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\DeleteAddressAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class DeleteAddressController
{
    public function __construct(private readonly DeleteAddressAction $action) {}

    public function __invoke(Request $request): Response
    {
        $this->action->handle($request->user());

        return response()->noContent();
    }
}
