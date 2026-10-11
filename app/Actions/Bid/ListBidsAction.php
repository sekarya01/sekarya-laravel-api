<?php

declare(strict_types=1);

namespace App\Actions\Bid;

use App\Data\CursorPageData;
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
    public function forTask(
        Task $task,
        CursorPageData $page,
        string $sort = 'amount',
        ?User $viewer = null,
    ): CursorPaginator {
        $base = Bid::query()->where('bids.task_id', $task->getKey());

        // Pengurutan berdasarkan rating butuh join — tidak bisa dari kolom bid sendiri.
        //
        // LEFT join, bukan inner: reputasi ada di `user_workers`, dan baris itu
        // baru lahir saat orangnya pertama kali menang atau mengisi profil
        // pekerjanya. Inner join akan MENGHILANGKAN penawaran dari pekerja
        // baru — tanpa galat, dan justru pada urutan yang dipakai pemberi
        // kerja untuk memilih orang.
        //
        // Dibungkus TABEL TURUNAN dengan COALESCE(…, 0): kolom urut cursor harus
        // kolom sungguhan yang ikut terpilih dan tidak pernah NULL. Dulu kolom
        // join dipakai langsung — tidak ter-select dan NULL untuk pekerja tanpa
        // profil — sehingga cursor halaman 2 berisi null dan kuerinya meledak
        // 500. Pekerja tanpa profil (rating 0) tetap jatuh ke bawah.
        if ($sort === 'rating') {
            $base = Bid::query()->fromSub(
                $base
                    ->leftJoin('user_workers', 'user_workers.user_id', '=', 'bids.bidder_id')
                    ->select('bids.*')
                    ->selectRaw('COALESCE(user_workers.worker_rating_avg, 0) AS rating_sort')
                    ->selectRaw('COALESCE(user_workers.tasks_completed, 0) AS completed_sort'),
                'bids',
            );
        }

        $query = $base
            // Callback eager-load pada relasi menerima Relation, bukan Builder.
            ->with([
                'bidder' => fn (Relation $q) => $q->withIdentityVerified(),
                // Keahlian penawar — dibaca pemberi kerja di layar profil
                // penawar. PublicUserResource hanya mengirimnya bila dimuat.
                'bidder.skills',
            ]);

        if ($sort === 'rating') {
            $query->orderByDesc('rating_sort')->orderByDesc('completed_sort');
        } elseif ($sort === 'amount') {
            $query->orderBy('bids.amount');
        } else {
            $query->orderByDesc('bids.created_at');
        }

        // Tiebreaker wajib: tanpa kolom unik, cursor bisa skip atau mengulang baris.
        $query->orderByDesc('bids.id');

        $bids = $query->cursorPaginate($page->perPage);

        // Jarak tiap pelamar ke lokasi task (U8) — hanya untuk pemberi kerja
        // (Task::showsWorkerDistanceTo). Dihitung di PHP dari profil pekerja
        // yang sudah termuat (User::$with), jadi nol kueri tambahan.
        // Nilainya atribut sementara yang tidak pernah disimpan; di jalur lain
        // (my_bid, respons tawar) ia tidak pernah diisi, sehingga
        // BidResource mengeluarkan `null` di sana.
        if ($task->showsWorkerDistanceTo($viewer)) {
            foreach ($bids->items() as $bid) {
                $bid->setAttribute(
                    'distance_km',
                    $bid->bidder === null ? null : $task->distanceToWorkerKm($bid->bidder),
                );
            }
        }

        return $bids;
    }
}
