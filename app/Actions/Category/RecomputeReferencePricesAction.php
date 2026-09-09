<?php

declare(strict_types=1);

namespace App\Actions\Category;

use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Database\ConnectionInterface;

/**
 * Menghitung ulang harga referensi per kategori.
 *
 * Sumbernya `agreed_amount` dari task yang SELESAI — bukan budget yang diminta
 * pemberi kerja (bisa terlalu rendah) dan bukan penawaran yang masuk (banyak
 * yang kalah lelang). Yang pantas dibayarkan adalah yang benar-benar disepakati
 * dan pekerjaannya beres.
 *
 * Memakai MEDIAN, bukan rata-rata: satu task Rp5.000.000 merusak rata-rata.
 */
final class RecomputeReferencePricesAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(): int
    {
        $updated = 0;

        foreach (Category::query()->cursor() as $category) {
            // Harga PER ORANG, dari penawaran yang diterima — bukan
            // `tasks.agreed_amount`, yang sejak task bisa merekrut banyak orang
            // berarti TOTAL seluruh pekerja. Memakai total akan memasukkan satu
            // task 30 orang sebagai satu "harga" tiga puluh kali lipat dan
            // menggeser median kategori itu jauh dari kenyataan.
            $amounts = Bid::query()
                ->join('tasks', 'tasks.id', '=', 'bids.task_id')
                ->where('tasks.category_id', $category->getKey())
                ->where('tasks.status', TaskStatus::Completed)
                ->where('bids.status', BidStatus::Accepted)
                ->orderBy('bids.amount')
                ->pluck('bids.amount')
                ->all();

            if ($amounts === []) {
                continue;
            }

            $this->db->transaction(function () use ($category, $amounts): void {
                $category->forceFill([
                    'ref_price_min' => $amounts[0],
                    'ref_price_max' => $amounts[count($amounts) - 1],
                    'ref_price_median' => $this->median($amounts),
                    // > 0 memberi tahu UI bahwa ini data nyata, bukan nilai seed.
                    'ref_sample_size' => count($amounts),
                    'ref_computed_at' => now(),
                ])->save();
            });

            $updated++;
        }

        return $updated;
    }

    /** @param list<int> $sorted */
    private function median(array $sorted): int
    {
        $n = count($sorted);
        $mid = intdiv($n, 2);

        return $n % 2 === 1
            ? $sorted[$mid]
            : (int) round(($sorted[$mid - 1] + $sorted[$mid]) / 2);
    }
}
