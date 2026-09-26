<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Data\Review\ReviewQueryData;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Review;
use App\Models\User;
use App\Support\SearchTerms;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Ulasan yang DITERIMA seseorang — layar "Semua Ulasan" dan profil publik.
 *
 * Penyaring bintang (`rating` / `rating_max`) memakai kolom biasa di bawah
 * indeks `(reviewee_id, reviewer_role, is_visible, created_at)`; pencarian
 * komentar lewat indeks FULLTEXT `review_search` sebagai subquery
 * NON-terkorelasi — pola yang sama dengan pencarian nama task.
 */
final class ListUserReviewsAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly SearchTerms $terms,
    ) {}

    /** @return CursorPaginator<int, Review> */
    public function forUser(User $user, ReviewQueryData $query = new ReviewQueryData): CursorPaginator
    {
        return Review::query()
            ->receivedBy($user, $query->role)
            ->when($query->rating !== null, fn (Builder $q) => $q->where('rating', $query->rating))
            ->when($query->ratingMax !== null, fn (Builder $q) => $q->where('rating', '<=', $query->ratingMax))
            ->when($query->keyword !== null, fn (Builder $q) => $this->applyKeyword($q, (string) $query->keyword))
            // Chip "Dengan Foto" (B15). JSON_LENGTH(NULL) → NULL, jadi
            // "tanpa foto" = NULL atau 0.
            ->when($query->hasPhotos === true, fn (Builder $q) => $q->whereRaw('JSON_LENGTH(reviews.photos) > 0'))
            ->when($query->hasPhotos === false, fn (Builder $q) => $q->whereRaw('COALESCE(JSON_LENGTH(reviews.photos), 0) = 0'))
            // task.category: judul + kategori pekerjaan di tiap kartu ulasan,
            // dua kueri per halaman berapa pun barisnya — bukan satu per baris.
            ->with([
                // Badge terverifikasi penilai dari satu subquery — tanpa ini
                // PublicUserResource menjalankan satu `exists` per baris.
                'reviewer' => fn (Relation $q) => $q->withCount([
                    'verifications as identity_verified_count' => fn (Builder $v) => $v
                        ->where('type', VerificationType::Identity)
                        ->where('status', VerificationStatus::Verified),
                ]),
                'task.category',
            ])
            ->latestFirst()
            ->cursorPaginate($query->page->perPage);
    }

    /** @param Builder<Review> $query */
    private function applyKeyword(Builder $query, string $keyword): void
    {
        $expression = $this->terms->forQuery($keyword);

        if ($expression === null) {
            // Seluruhnya tanda baca: nol hasil, bukan "tanpa penyaring".
            $query->whereRaw('1 = 0');

            return;
        }

        $reviewIds = $this->db->table('review_search')
            ->whereRaw('MATCH(terms) AGAINST (? IN BOOLEAN MODE)', [$expression])
            ->select('review_id');

        $query->whereIn('reviews.id', $reviewIds);
    }
}
