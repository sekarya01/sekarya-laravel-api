<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Profil publik satu orang (`GET users/{user}`) — dibuka dari notifikasi atau
 * tautan, bukan hanya dari kartu yang dititipkan layar sebelumnya.
 *
 * HANYA akun `active`. Akun yang belum memverifikasi email, ditangguhkan, atau
 * di-ban dijawab PERSIS seperti ULID yang tidak ada (404 yang sama, pesan yang
 * sama) — membedakannya berarti mengonfirmasi bahwa orang itu ada, dan bahwa
 * ia sedang dimoderasi. Aturannya sama dengan `GET /workers`: yang menyaring
 * hanya status akun, bukan verifikasi identitas (itu MENANDAI lewat
 * `identity_verified` / `ready_to_work`).
 *
 * Pencarian dilakukan di sini, bukan lewat route model binding: binding akan
 * meresolusi akun apa pun dan penyaringan status sesudahnya mudah terlupa di
 * rute berikutnya yang menyematkan `{user}`.
 */
final class ShowPublicUserAction
{
    /** @throws ModelNotFoundException<User> */
    public function handle(string $ulid): User
    {
        return User::query()
            ->where('ulid', $ulid)
            ->where('status', UserStatus::Active)
            ->with('skills')
            // Badge terverifikasi dari satu subquery, bukan kueri terpisah
            // yang dipanggil dua kali (identity_verified dan ready_to_work).
            ->withCount([
                'verifications as identity_verified_count' => fn (Builder $v) => $v
                    ->where('type', VerificationType::Identity)
                    ->where('status', VerificationStatus::Verified),
            ])
            ->firstOrFail();
    }
}
