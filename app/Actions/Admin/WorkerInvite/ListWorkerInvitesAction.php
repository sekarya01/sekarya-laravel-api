<?php

declare(strict_types=1);

namespace App\Actions\Admin\WorkerInvite;

use App\Data\CursorPageData;
use App\Models\WorkerInviteCode;
use App\Models\WorkerInviteRedemption;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Daftar kode undangan mitra & pemakainya (API pengelola).
 *
 * Diurutkan `id` saja, terbaru dulu: `created_at` kedua tabel NULLABLE, dan
 * kolom urut cursor yang bisa NULL membuat halaman berikutnya rusak. `id`
 * unik dan naik bersama waktu, jadi cukup sebagai satu-satunya kunci keyset.
 */
final class ListWorkerInvitesAction
{
    /** @return CursorPaginator<int, WorkerInviteCode> */
    public function codes(CursorPageData $page): CursorPaginator
    {
        return WorkerInviteCode::query()
            ->withCount('redemptions')
            ->with('creator')
            ->orderByDesc('id')
            ->cursorPaginate($page->perPage);
    }

    /**
     * Siapa saja yang memakai kode ini — dasar meja verifikasi: pengelola
     * melihat pekerja yang masuk lewat kode, lalu memverifikasi identitasnya
     * di antrean verifikasi seperti biasa.
     *
     * @return CursorPaginator<int, WorkerInviteRedemption>
     */
    public function redemptions(WorkerInviteCode $code, CursorPageData $page): CursorPaginator
    {
        return WorkerInviteRedemption::query()
            ->where('invite_code_id', $code->getKey())
            ->with(['user.verifications'])
            ->orderByDesc('id')
            ->cursorPaginate($page->perPage);
    }
}
