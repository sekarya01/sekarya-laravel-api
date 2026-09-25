<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\UpsertAddressData;
use App\Models\User;
use App\Models\UserAddress;

/**
 * Buat atau ganti alamat tersimpan milik sendiri (B6).
 *
 * Satu baris per orang, dijamin `user_addresses.user_id` unique; `updateOrCreate`
 * di atas kunci itu membuat PUT yang sama dua kali menghasilkan keadaan yang
 * sama. `user_id` tidak mass-assignable — ia datang dari aktor, tidak pernah
 * dari payload.
 */
final class UpsertAddressAction
{
    public function handle(UpsertAddressData $data, User $user): UserAddress
    {
        return $user->savedAddress()->updateOrCreate([], $data->toAttributes());
    }
}
