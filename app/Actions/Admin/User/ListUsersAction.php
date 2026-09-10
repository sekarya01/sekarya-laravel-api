<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Data\Admin\UserQueueData;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Daftar pengguna untuk moderasi.
 *
 * Dua penyaring, keduanya bertumpu indeks yang sudah ada:
 * `status` (indeks status, active_mode) dan `email` (indeks unique).
 *
 * TIDAK ada pencarian nama sebagian. `LIKE '%budi%'` tidak bisa memakai
 * indeks apa pun, jadi setiap ketikan di kotak pencarian akan memindai
 * seluruh tabel pengguna — dan proyek ini memang tidak memakai `LIKE` di mana
 * pun. Pencarian nama yang benar butuh tabel indeks terbalik dengan
 * normalisasi di dua sisi, seperti `task_search`; itu slice sendiri.
 */
final class ListUsersAction
{
    /** @return CursorPaginator<int, User> */
    public function handle(UserQueueData $data): CursorPaginator
    {
        return User::query()
            ->when($data->status !== null, fn ($q) => $q->where('status', $data->status))
            ->when($data->email !== null, fn ($q) => $q->where('email', $data->email))
            ->latestFirst()
            ->cursorPaginate($data->page->perPage);
    }
}
