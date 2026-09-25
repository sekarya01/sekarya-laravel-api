<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\ShowPublicUserAction;
use App\Http\Resources\Api\V1\PublicUserResource;

/**
 * Profil publik — PublicUserResource yang sama dengan yang disematkan di
 * `poster`, `workers[]`, dan `bid.bidder`, ditambah keahliannya. Tidak pernah
 * email, nomor HP, alamat, atau koordinat.
 *
 * `{user}` sengaja diterima sebagai string (tanpa route model binding) — lihat
 * ShowPublicUserAction untuk alasannya.
 */
final class ShowPublicUserController
{
    public function __construct(private readonly ShowPublicUserAction $action) {}

    public function __invoke(string $user): PublicUserResource
    {
        return PublicUserResource::make($this->action->handle($user));
    }
}
