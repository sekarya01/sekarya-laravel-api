<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Database\Eloquent\Collection;

/** Pengajuan verifikasi milik sendiri — sedikit per orang, jadi tanpa halaman. */
final class ListMyVerificationsAction
{
    /** @return Collection<int, UserVerification> */
    public function handle(User $user): Collection
    {
        return $user->verifications()->latest('submitted_at')->get();
    }
}
