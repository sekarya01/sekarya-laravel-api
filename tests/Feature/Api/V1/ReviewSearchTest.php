<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Actions\Review\ListUserReviewsAction;
use App\Data\Review\ReviewQueryData;
use App\Enums\TaskStatus;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `GET users/{user}/reviews?q=` — pencarian komentar lewat FULLTEXT
 * `review_search` (U7).
 *
 * DatabaseTruncation, bukan RefreshDatabase: indeks FULLTEXT InnoDB baru
 * diperbarui saat COMMIT, jadi di bawah transaksi yang di-rollback setiap
 * pencarian mengembalikan nol baris dan terlihat persis seperti bug aplikasi.
 * Karena kelas ini meninggalkan baris ter-commit, setiap assertion dibatasi ke
 * orang yang dibuat test itu sendiri.
 */
final class ReviewSearchTest extends TestCase
{
    use DatabaseTruncation;

    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->worker = $this->activeUser();
    }

    private function review(User $reviewee, ?string $comment, int $rating = 5): Review
    {
        return Review::factory()->create([
            'task_id' => Task::factory()->create([
                'poster_id' => $this->activeUser()->getKey(),
                'category_id' => $this->anyCategory()->getKey(),
                'status' => TaskStatus::Completed,
            ])->getKey(),
            'reviewee_id' => $reviewee->getKey(),
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    /** @return list<int> */
    private function search(string $query, User $reviewee, array $extra = []): array
    {
        return collect(
            $this->asUser($this->activeUser())
                ->getJson(route('v1.users.reviews.index', $reviewee).'?'.http_build_query(['q' => $query, ...$extra]))
                ->assertOk()
                ->json('data'),
        )->pluck('id')->sort()->values()->all();
    }

    public function test_comment_words_are_found_including_affixed_forms(): void
    {
        $tidy = $this->review($this->worker, 'Kerjanya rapi sekali dan tepat waktu');
        $clean = $this->review($this->worker, 'Membersihkan kamar mandi sampai kinclong');
        $this->review($this->worker, 'Datang terlambat');

        $this->assertSame([$tidy->id], $this->search('rapi', $this->worker));
        // Akar kata ikut diindeks: "bersih" bertemu "membersihkan".
        $this->assertSame([$clean->id], $this->search('bersih', $this->worker));
        // Kata terakhir diperlakukan sebagai awalan, seperti sedang mengetik.
        $this->assertSame([$tidy->id], $this->search('tepat wak', $this->worker));
    }

    public function test_search_is_scoped_to_the_reviewee_and_combines_with_rating(): void
    {
        $mine5 = $this->review($this->worker, 'Pekerjaan rapi', 5);
        $this->review($this->worker, 'Kurang rapi', 2);
        $this->review($this->activeUser(), 'Sangat rapi', 5);

        $this->assertSame([$mine5->id], $this->search('rapi', $this->worker, ['rating' => 5]));
        $this->assertCount(2, $this->search('rapi', $this->worker));
    }

    public function test_punctuation_only_returns_nothing_rather_than_everything(): void
    {
        $this->review($this->worker, 'Rapi');

        $this->assertSame([], $this->search('???', $this->worker));
    }

    public function test_blank_q_is_no_filter(): void
    {
        $this->review($this->worker, 'Rapi');
        $this->review($this->worker, null);

        $this->assertCount(2, $this->search('', $this->worker));
    }

    public function test_editing_or_clearing_a_comment_keeps_the_index_in_step(): void
    {
        $review = $this->review($this->worker, 'Rapi sekali');

        $review->update(['comment' => 'Ramah dan sopan']);
        $this->assertSame([], $this->search('rapi', $this->worker));
        $this->assertSame([$review->id], $this->search('ramah', $this->worker));

        $review->update(['comment' => null]);
        $this->assertFalse(DB::table('review_search')->where('review_id', $review->id)->exists());
    }

    public function test_the_query_never_uses_like_and_reads_the_fulltext_index(): void
    {
        $this->review($this->worker, 'Rapi');

        DB::enableQueryLog();
        app(ListUserReviewsAction::class)->forUser($this->worker, new ReviewQueryData(keyword: 'rapi'));
        $sql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();

        $this->assertStringNotContainsStringIgnoringCase('like', $sql);
        $this->assertStringContainsString('MATCH(terms) AGAINST', $sql);

        $plan = implode("\n", array_map(
            static fn (object $row): string => (string) array_values((array) $row)[0],
            DB::select(
                "EXPLAIN FORMAT=TREE SELECT review_id FROM review_search WHERE MATCH(terms) AGAINST ('+rapi*' IN BOOLEAN MODE)",
            ),
        ));
        $this->assertStringContainsString('Full-text index search on review_search', $plan, $plan);
    }
}
