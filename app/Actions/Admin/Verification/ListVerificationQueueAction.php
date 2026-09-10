<?php

declare(strict_types=1);

namespace App\Actions\Admin\Verification;

use App\Data\Admin\VerificationQueueData;
use App\Enums\VerificationStatus;
use App\Models\UserVerification;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Antrean penilaian identitas & rekening.
 *
 * Urutannya PALING LAMA MENUNGGU DI DEPAN — kebalikan dari seluruh endpoint
 * daftar lain di API ini, yang menampilkan terbaru dulu. Antrean kerja yang
 * menampilkan terbaru dulu membuat pengajuan tertua tenggelam makin dalam
 * setiap kali ada pengajuan baru, dan justru pengajuan itulah yang paling
 * lama membuat orang tidak bisa bekerja.
 */
final class ListVerificationQueueAction
{
    /** @return CursorPaginator<int, UserVerification> */
    public function handle(VerificationQueueData $data): CursorPaginator
    {
        return UserVerification::query()
            ->when(
                $data->status !== null,
                fn ($q) => $q->where('status', $data->status),
                // Bawaan: yang masih menunggu keputusan. whereIn atas dua
                // nilai tetap memakai indeks (status, submitted_at).
                fn ($q) => $q->whereIn('status', [
                    VerificationStatus::Pending,
                    VerificationStatus::InReview,
                ]),
            )
            ->when($data->type !== null, fn ($q) => $q->where('type', $data->type))
            // Nama pengajunya ikut ditampilkan di daftar; tanpa eager load,
            // satu halaman 20 baris jadi 21 kueri.
            ->with('user')
            ->queueOrder()
            ->cursorPaginate($data->page->perPage);
    }
}
