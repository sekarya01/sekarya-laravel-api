<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;

/** Hapus alamat tersimpan milik sendiri. Idempoten: tidak ada = bukan galat. */
final class DeleteAddressAction
{
    public function handle(User $user): void
    {
        $user->savedAddress()->delete();
    }
}
