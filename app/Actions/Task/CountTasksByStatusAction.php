<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hitungan task per status untuk judul tab "Berjalan (2)", "Selesai (5)" (B7).
 *
 * DIHITUNG SERVER: daftarnya bercursor tanpa `total`, jadi menghitung baris
 * yang kebetulan sudah dimuat di klien menghasilkan angka yang berubah setiap
 * kali digulir. Setiap status SELALU ada (nol bila tidak ada baris), supaya
 * klien tidak perlu membedakan "kunci hilang" dari "nol".
 */
final class CountTasksByStatusAction
{
    /** @return array<string, int> */
    public function postedBy(User $poster): array
    {
        return $this->counts(Task::query()->where('tasks.poster_id', $poster->getKey()));
    }

    /** @return array<string, int> */
    public function workedBy(User $worker): array
    {
        return $this->counts(Task::query()->whereExists(fn ($q) => $q
            ->selectRaw('1')
            ->from('bids')
            ->whereColumn('bids.task_id', 'tasks.id')
            ->where('bids.bidder_id', $worker->getKey())
            ->where('bids.status', BidStatus::Accepted->value)));
    }

    /**
     * @param  Builder<Task>  $query
     * @return array<string, int>
     */
    private function counts(Builder $query): array
    {
        /** @var array<string, int> $rows */
        $rows = $query
            ->toBase()
            ->groupBy('tasks.status')
            ->selectRaw('tasks.status, COUNT(*) AS aggregate')
            ->pluck('aggregate', 'status')
            ->map(static fn (mixed $n): int => (int) $n)
            ->all();

        $out = [];
        foreach (TaskStatus::cases() as $status) {
            $out[$status->value] = $rows[$status->value] ?? 0;
        }

        return $out;
    }
}
