<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Wallet\WalletEntryQueryData;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskFundMovement;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * Riwayat mutasi saldo seseorang.
 *
 * Berangkat dari `wallet_entries` yang disaring `wallet_id`, bukan lewat
 * relasi dari dompet yang mungkin belum ada. Orang yang belum pernah punya
 * mutasi tetap mendapat halaman kosong yang sah — bukan 404 atas dompet yang
 * memang belum perlu dibuat.
 */
final class ListWalletEntriesAction
{
    /** @return CursorPaginator<int, WalletEntry> */
    public function handle(User $user, WalletEntryQueryData $data): CursorPaginator
    {
        $walletId = $user->walletOrNew()->getKey();

        $page = WalletEntry::query()
            // Dompet yang belum ada tidak punya id, dan `where(col, null)`
            // diterjemahkan Eloquent jadi `col IS NULL` — pada kolom NOT NULL
            // itu tidak pernah cocok, jadi hasilnya halaman kosong yang sah.
            // Bukan 404: tidak punya riwayat bukan kesalahan.
            ->where('wallet_id', $walletId)
            ->when($data->type !== null, fn ($q) => $q->where('type', $data->type))
            ->when($data->direction !== null, fn ($q) => $q->where('direction', $data->direction))
            ->when($data->types !== [], fn ($q) => $q->whereIn('type', $data->types))
            ->when($data->from !== null, fn ($q) => $q->where('created_at', '>=', $data->from))
            ->when($data->to !== null, fn ($q) => $q->where('created_at', '<', $data->to))
            ->when($data->minAmount !== null, fn ($q) => $q->where('amount', '>=', $data->minAmount))
            ->when($data->maxAmount !== null, fn ($q) => $q->where('amount', '<=', $data->maxAmount))
            // LIKE — PENGECUALIAN tercatat dari aturan "tanpa LIKE" proyek ini.
            // Aturan itu ada karena LIKE '%x%' memindai SELURUH tabel. Di sini
            // `wallet_id` sudah mempersempit lewat indeks ke riwayat SATU
            // orang, jadi yang dipindai hanya baris miliknya (lihat EXPLAIN
            // di test). `%`/`_` dari ketikan di-escape agar dibaca harfiah.
            //
            // Judul task ikut dicocokkan: baris riwayat di aplikasi DITAMPILKAN
            // dengan judul task-nya (`attachTasks`), sedangkan `description`
            // hanya "Dana tugas #123 ditahan". Tanpa ini, mengetik judul yang
            // terlihat di layar selalu berujung kosong. Subkueri berkorelasi
            // lewat primary key rujukan — tetap per baris milik orang ini,
            // bukan pemindaian `tasks`.
            ->when($data->search !== null && $data->search !== '', function ($q) use ($data): void {
                $like = '%'.addcslashes((string) $data->search, '%_\\').'%';

                $q->where(function ($q) use ($like): void {
                    $q->where('description', 'like', $like);

                    foreach (array_keys(self::taskReferences()) as $table) {
                        $q->orWhereExists(fn ($sub) => $sub->selectRaw('1')
                            ->from($table.' as ref')
                            ->join('tasks as ref_task', 'ref_task.id', '=', 'ref.task_id')
                            ->whereColumn('ref.id', 'wallet_entries.reference_id')
                            ->where('wallet_entries.reference_type', $table)
                            ->where('ref_task.title', 'like', $like));
                    }
                });
            })
            ->latestFirst()
            ->cursorPaginate($data->page->perPage);

        $this->attachTasks($page->getCollection());

        return $page;
    }

    /**
     * Rujukan kejadian penyebab → task yang bersangkutan, untuk judul baris
     * ("Pindahan Lemari · Dana ditahan") dan tautan ke detailnya.
     *
     * Kueri TETAP per halaman — satu per jenis rujukan ditambah satu untuk
     * task-nya — berapa pun jumlah barisnya. `reference_type` adalah slug
     * tabel, bukan nama kelas, jadi tidak ada morph map yang bisa dipakai
     * `morphTo`; menambahkannya secara global akan ikut mengubah cara kolom
     * polimorfik lain (token Sanctum) ditulis.
     *
     * Task terhapus lunak tetap disebut: riwayat uang tidak boleh kehilangan
     * keterangannya karena task-nya dibersihkan.
     *
     * @param  Collection<int, WalletEntry>  $entries
     */
    private function attachTasks(Collection $entries): void
    {
        $sources = self::taskReferences();

        $taskIdByReference = [];

        foreach ($sources as $table => $model) {
            $ids = $entries
                ->where('reference_type', $table)
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                continue;
            }

            $query = $model::query();

            // `payments` terhapus lunak: rujukannya tetap harus terbaca.
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $query->withTrashed();
            }

            foreach ($query->whereIn('id', $ids)->pluck('task_id', 'id') as $id => $taskId) {
                $taskIdByReference[$table.':'.$id] = (int) $taskId;
            }
        }

        $tasks = $taskIdByReference === []
        ? collect()
        : Task::query()
            ->withTrashed()
            // `category` ikut supaya baris riwayat bisa menyebut
            // "Pindahan & Angkut" tanpa satu kueri per baris (U3).
            ->with('category')
            ->whereIn('id', array_unique(array_values($taskIdByReference)))
            ->get(['id', 'ulid', 'task_number', 'title', 'category_id'])
            ->keyBy('id');

        foreach ($entries as $entry) {
            $taskId = $taskIdByReference[$entry->reference_type.':'.$entry->reference_id] ?? null;

            $entry->setRelation('task', $taskId === null ? null : $tasks->get($taskId));
        }
    }

    /**
     * Jenis rujukan buku besar yang menunjuk ke task — slug tabel => model.
     * Dipakai menamai baris (`attachTasks`) DAN mencarinya, supaya keduanya
     * tidak bisa berbeda daftar.
     *
     * @return array<string, class-string<Model>>
     */
    private static function taskReferences(): array
    {
        return [
            (new TaskFundMovement)->getTable() => TaskFundMovement::class,
            (new Activity)->getTable() => Activity::class,
            (new Payment)->getTable() => Payment::class,
        ];
    }
}
