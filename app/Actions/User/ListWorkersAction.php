<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\ListWorkersData;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\UserWorker;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Daftar pekerja yang siap menerima pekerjaan.
 *
 * Kuerinya berangkat dari `user_workers`, BUKAN dari `users` yang disaring
 * "punya profil pekerja". Dua alasan, dan keduanya soal kebenaran, bukan
 * selera:
 *
 * 1. **Cursor harus dihitung dari tabel yang diurutkan.** Kalau kuerinya
 *    berangkat dari `users`, kolom urutannya `users.created_at` — dan yang
 *    dicari pemberi kerja adalah orang yang baru siap bekerja, bukan orang
 *    yang baru mendaftar.
 * 2. **Memetakan hasil paginator ke model lain merusak cursor secara diam-diam.**
 *    `CursorPaginator` membaca kolom urutannya DARI ITEM-nya saat menyusun
 *    `next_cursor`. Item yang sudah ditukar jadi `User` tetap punya
 *    `created_at` dan `id` — jadi tidak ada galat, hanya nilai cursor yang
 *    salah, dan halaman berikutnya melompati orang.
 *
 * Hanya akun `active` yang muncul. Akun yang ditangguhkan atau diblokir masih
 * punya baris profil, dan daftar ini adalah tempat pemberi kerja memilih orang
 * untuk dihubungi.
 */
final class ListWorkersAction
{
    /** @return CursorPaginator<int, UserWorker> */
    public function handle(ListWorkersData $data): CursorPaginator
    {
        return UserWorker::query()
            ->whereHas('user', fn (Builder $q) => $q
                ->where('status', UserStatus::Active)
                ->when($data->gender !== null, fn (Builder $g) => $g->where('gender', $data->gender)))
            ->when($data->city !== null, fn (Builder $q) => $q->whereResolvedAddress('city', $data->city))
            ->when($data->province !== null, fn (Builder $q) => $q->whereResolvedAddress('province', $data->province))
            // Pemiliknya ikut termuat berikut bahan pertimbangannya. Tanpa ini
            // setiap baris memicu tiga kueri tambahan, dan halaman 50 orang
            // jadi 150 kueri — benar hasilnya, dan tetap tidak bisa dipakai.
            ->with(['user' => fn (Relation $q) => $q
                ->with('skills')
                ->withCount([
                    'verifications as identity_verified_count' => fn (Builder $v) => $v
                        ->where('type', VerificationType::Identity)
                        ->where('status', VerificationStatus::Verified),
                ])])
            ->latestFirst()
            ->cursorPaginate($data->page->perPage);
    }
}
