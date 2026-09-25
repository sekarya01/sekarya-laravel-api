<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Data\Review\ReviewSummary;
use App\Data\Review\ReviewSummaryQueryData;
use App\Models\Review;
use App\Models\User;

/**
 * "★4.9 dari 41", "92% 5 Bintang", dan angka di setiap chip bintang.
 *
 * Dihitung dari tabel `reviews`, BUKAN dari agregat tersimpan
 * (`user_workers.worker_rating_*`, `users.poster_rating_*`). Alasannya:
 * ringkasan ini boleh tanpa `role` (gabungan dua arah) dan harus cocok persis
 * dengan daftar di bawahnya — keduanya memakai `Review::scopeReceivedBy()`.
 * Agregat tersimpan tetap satu-satunya yang dipakai untuk pengurutan.
 *
 * Satu kueri `GROUP BY rating`; rata-rata diturunkan dari distribusinya, jadi
 * tiga angka di layar tidak bisa saling berselisih.
 */
final class SummarizeUserReviewsAction
{
    public function handle(User $user, ReviewSummaryQueryData $query = new ReviewSummaryQueryData): ReviewSummary
    {
        /** @var array<int, int> $rows */
        $rows = Review::query()
            ->receivedBy($user, $query->role)
            ->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating')
            ->map(static fn (mixed $n): int => (int) $n)
            ->all();

        // Kunci 5..1 selalu ada — bintang tanpa ulasan adalah 0, bukan kunci
        // yang hilang.
        $distribution = [];
        foreach ([5, 4, 3, 2, 1] as $star) {
            $distribution[$star] = $rows[$star] ?? 0;
        }

        $count = array_sum($distribution);
        $weighted = 0;
        foreach ($distribution as $star => $n) {
            $weighted += $star * $n;
        }

        return new ReviewSummary(
            role: $query->role,
            average: $count === 0 ? 0.0 : round($weighted / $count, 2),
            count: $count,
            distribution: $distribution,
            fiveStarPercent: $count === 0 ? 0 : (int) round($distribution[5] * 100 / $count),
        );
    }
}
