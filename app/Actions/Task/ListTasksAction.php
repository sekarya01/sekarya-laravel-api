<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Data\Task\ListTasksData;
use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskSearch;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

final class ListTasksAction
{
    public function __construct(private readonly TaskSearch $search) {}

    /**
     * Feed "pekerjaan siap dilamar" untuk mode cari kerja.
     *
     * Urutan default: tanggal pembuatan terbaru. Kata kunci, jarak, dan
     * keahlian adalah FILTER — bukan pengubah urutan — sehingga cursor
     * pagination tetap bertumpu pada kolom berindeks dan hasilnya stabil.
     *
     * Tiga pengecualian yang wajib, kalau tidak feed berbohong:
     *  1. Hanya yang benar-benar bisa dilamar (scope biddable) — status open
     *     TIDAK cukup, masa lelang bisa sudah lewat.
     *  2. Bukan task milik sendiri — di app ini satu orang bisa di kedua sisi.
     *  3. Tawaran sendiri disertakan sebagai `my_bid` agar UI bisa menandai
     *     "sudah dilamar" tanpa panggilan kedua.
     *
     * @return CursorPaginator<int, Task>
     */
    public function open(ListTasksData $data, User $actor): CursorPaginator
    {
        $query = $this->base($data)
            ->biddable()
            ->where('tasks.poster_id', '!=', $actor->getKey())
            ->when(
                $data->excludeMyBids,
                fn (Builder $q) => $q->whereDoesntHave(
                    'bids',
                    fn (Builder $b) => $b->where('bidder_id', $actor->getKey()),
                ),
            )
            ->with(['myBid' => fn (Relation $q) => $q->where('bidder_id', $actor->getKey())]);

        $this->applySkills($query, $data, $actor);

        return $query->cursorPaginate($data->page->perPage);
    }

    /**
     * Task yang diposting seseorang.
     *
     * @return CursorPaginator<int, Task>
     */
    public function postedBy(ListTasksData $data, User $poster): CursorPaginator
    {
        return $this->base($data)
            ->where('tasks.poster_id', $poster->getKey())
            ->when($data->status, fn (Builder $q, TaskStatus $s) => $q->where('tasks.status', $s))
            ->cursorPaginate($data->page->perPage);
    }

    /**
     * Task yang dikerjakan seseorang.
     *
     * @return CursorPaginator<int, Task>
     */
    public function workedBy(ListTasksData $data, User $worker): CursorPaginator
    {
        return $this->base($data)
            // Lewat `bids`, bukan kolom di `tasks`: satu task bisa merekrut
            // banyak orang. `bids` sudah punya indeks (bidder_id, status,
            // created_at), jadi subquery ini terarah, bukan pemindaian.
            ->whereExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('bids')
                ->whereColumn('bids.task_id', 'tasks.id')
                ->where('bids.bidder_id', $worker->getKey())
                ->where('bids.status', BidStatus::Accepted->value))
            ->when($data->status, fn (Builder $q, TaskStatus $s) => $q->where('tasks.status', $s))
            ->cursorPaginate($data->page->perPage);
    }

    /** @return Builder<Task> */
    private function base(ListTasksData $data): Builder
    {
        // select eksplisit dan seluruh kolom dikualifikasi dengan `tasks.`:
        // filter jarak menambahkan kolom terhitung, dan pengurutan penawaran
        // memakai join ke users — tanpa kualifikasi, kolomnya bisa ambigu.
        $query = Task::query()
            ->select('tasks.*')
            ->with(['category', 'poster', 'skills'])
            ->when($data->categoryId, fn (Builder $q, int $id) => $q->where('tasks.category_id', $id))
            ->when($data->city, fn (Builder $q, string $c) => $q->where('tasks.city', $c))
            // budget_max nullable, jadi acuan filter adalah budget_min.
            ->when($data->budgetFrom, fn (Builder $q, int $v) => $q->where('tasks.budget_min', '>=', $v))
            ->when($data->budgetTo, fn (Builder $q, int $v) => $q->where('tasks.budget_min', '<=', $v))
            // Batas waktu dihitung dari jam server, bukan dikirim klien:
            // sebuah tanggal dari perangkat yang jamnya meleset akan
            // menyembunyikan task yang sebenarnya baru.
            ->when(
                $data->postedWithinHours,
                fn (Builder $q, int $hours) => $q->where(
                    'tasks.created_at',
                    '>=',
                    now()->subHours($hours),
                ),
            );

        if ($data->keyword !== null) {
            $this->search->applyKeyword($query, $data->keyword);
        }

        if ($data->hasCoordinates()) {
            $this->search->applyRadius(
                $query,
                $data->latitude,
                $data->longitude,
                $data->radiusKm,
            );
        }

        // Urutan default. `id` wajib sebagai tiebreaker cursor.
        return $query->orderByDesc('tasks.created_at')->orderByDesc('tasks.id');
    }

    /**
     * Filter keahlian. Lewat pivot berindeks — bukan JSON, bukan LIKE.
     *
     * @param  Builder<Task>  $query
     */
    private function applySkills(Builder $query, ListTasksData $data, User $actor): void
    {
        if ($data->skillSlugs !== []) {
            $this->search->applySkillSlugs($query, $data->skillSlugs);

            return;
        }

        if ($data->matchMySkills) {
            $this->search->applyMatchingSkills($query, (int) $actor->getKey());
        }
    }
}
