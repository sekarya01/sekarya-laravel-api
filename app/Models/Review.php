<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewerRole;
use App\Support\SearchTerms;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

final class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'task_id', 'reviewer_id', 'reviewee_id', 'reviewer_role',
        'rating', 'comment', 'tags', 'photos', 'is_visible',
    ];

    protected static function booted(): void
    {
        // Baris indeks pencarian komentar dijaga DI SINI, bukan di Action —
        // alasan yang sama dengan hook `saved` di Task: ada lebih dari satu
        // jalur yang menulis ulasan (Action, factory, seeder), dan indeks yang
        // meleset tidak menimbulkan galat; ulasan itu sekadar tak pernah
        // ditemukan.
        self::saved(function (self $review): void {
            if ($review->wasRecentlyCreated || $review->wasChanged('comment')) {
                $review->syncSearchIndex();
            }
        });
    }

    /**
     * Tulis ulang (atau hapus) baris `review_search` milik ulasan ini.
     *
     * Teks yang disimpan sudah dinormalisasi SearchTerms — kelas yang sama
     * dengan sisi kueri di ListUserReviewsAction. Ulasan tanpa komentar tidak
     * punya baris.
     */
    public function syncSearchIndex(): void
    {
        $terms = (new SearchTerms)->forIndex((string) $this->comment);

        if ($terms === '') {
            DB::table('review_search')->where('review_id', $this->getKey())->delete();

            return;
        }

        DB::table('review_search')->updateOrInsert(
            ['review_id' => $this->getKey()],
            ['terms' => $terms],
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reviewer_role' => ReviewerRole::class,
            'rating' => 'integer',
            // Daftar nilai ReviewTag. Disimpan sebagai string, bukan cast enum
            // per elemen: tag yang suatu hari dihapus dari enum tidak boleh
            // membuat ulasan lama gagal dibaca.
            'tags' => 'array',
            'photos' => 'array',
            'is_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }

    /** @param Builder<$this> $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    /**
     * Ulasan TAMPIL yang diterima seseorang, opsional per arah.
     *
     * Satu definisi untuk daftar ulasan DAN ringkasannya — supaya "41 ulasan"
     * di atas layar menghitung persis baris yang bisa digulir di bawahnya.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeReceivedBy(Builder $query, User $reviewee, ?ReviewerRole $role = null): void
    {
        $query
            ->where('reviewee_id', $reviewee->getKey())
            ->when($role, fn (Builder $q, ReviewerRole $r) => $q->where('reviewer_role', $r))
            ->visible();
    }

    /** @param Builder<$this> $query */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
