<?php

declare(strict_types=1);

namespace App\Actions\Bid;

use App\Data\CursorPageData;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ListBidsAction
{
    /**
     * Penawaran pada sebuah task — bahan pertimbangan pemberi kerja.
     *
     * Sinyal kepercayaan (rating, jumlah kerja, verifikasi) diambil dari user
     * penawar saat ditampilkan, bukan disalin ke baris bid: salinan akan basi.
     *
     * @return CursorPaginator<int, Bid>
     */
    public function forTask(Task $task, CursorPageData $page, string $sort = 'amount'): CursorPaginator
    {
        $query = Bid::query()
            ->where('task_id', $task->getKey())
            // Callback eager-load pada relasi menerima Relation, bukan Builder.
            ->with(['bidder' => fn (Relation $q) => $q->withCount([
                'verifications as identity_verified_count' => fn (Builder $v) => $v
                    ->where('type', VerificationType::Identity)
                    ->where('status', VerificationStatus::Verified),
            ])]);

        // Pengurutan berdasarkan rating butuh join — tidak bisa dari kolom bid sendiri.
        //
        // LEFT join, bukan inner: reputasi ada di `user_workers`, dan baris itu
        // baru lahir saat orangnya pertama kali menang atau mengisi profil
        // pekerjanya. Inner join akan MENGHILANGKAN penawaran dari pekerja
        // baru — tanpa galat, dan justru pada urutan yang dipakai pemberi
        // kerja untuk memilih orang. Yang belum punya profil jatuh ke bawah
        // sendirinya: di MySQL, NULL diurutkan paling akhir pada DESC.
        if ($sort === 'rating') {
            $query
                ->leftJoin('user_workers', 'user_workers.user_id', '=', 'bids.bidder_id')
                ->orderByDesc('user_workers.worker_rating_avg')
                ->orderByDesc('user_workers.tasks_completed')
                ->select('bids.*');
        } elseif ($sort === 'amount') {
            $query->orderBy('bids.amount');
        } else {
            $query->orderByDesc('bids.created_at');
        }

        // Tiebreaker wajib: tanpa kolom unik, cursor bisa skip atau mengulang baris.
        $query->orderByDesc('bids.id');

        return $query->cursorPaginate($page->perPage);
    }

    /**
     * Penawaran yang diikuti seseorang.
     *
     * @return CursorPaginator<int, Bid>
     */
    public function byBidder(User $bidder, CursorPageData $page): CursorPaginator
    {
        return Bid::query()
            ->where('bidder_id', $bidder->getKey())
            ->with(['task.category'])
            ->latestFirst()
            ->cursorPaginate($page->perPage);
    }
}
