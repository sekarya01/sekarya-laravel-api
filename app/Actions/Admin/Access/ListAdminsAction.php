<?php

declare(strict_types=1);

namespace App\Actions\Admin\Access;

use App\Data\CursorPageData;
use App\Models\Admin;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Daftar akun pengelola. Hanya super_admin (Policy di rute).
 *
 * Yang sudah dihapus tidak ikut: scope soft delete bawaan menyingkirkannya,
 * dan itu memang yang diinginkan — barisnya tetap ada hanya supaya jejak
 * audit yang menunjuknya tetap bisa dibaca.
 */
final class ListAdminsAction
{
    /** @return CursorPaginator<int, Admin> */
    public function handle(CursorPageData $page): CursorPaginator
    {
        return Admin::query()
            ->latestFirst()
            ->cursorPaginate($page->perPage);
    }
}
