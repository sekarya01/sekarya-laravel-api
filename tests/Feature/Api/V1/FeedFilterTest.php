<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TaskStatus;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Skill;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Feed pencari kerja lewat HTTP, termasuk seluruh filter.
 *
 * DatabaseTruncation karena indeks FULLTEXT InnoDB butuh transaksi yang
 * commit — lihat catatan di Tests\TestCase.
 */
final class FeedFilterTest extends TestCase
{
    use DatabaseTruncation;

    private User $seeker;

    private User $poster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->seeker = $this->activeUser();
        $this->poster = $this->activeUser();
    }

    private function task(array $attributes = [], array $skills = []): Task
    {
        $task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
            'budget_min' => 150_000,
            'budget_max' => null,
            ...$attributes,
        ]);

        if ($skills !== []) {
            $task->skills()->sync(Skill::query()->whereIn('slug', $skills)->pluck('id'));
        }

        return $task;
    }

    /** @return list<string> */
    private function feed(array $query = []): array
    {
        return $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', $query))
            ->assertOk()
            ->json('data.*.title');
    }

    // ── waktu: seberapa baru task diposting ─────────────────────────────────

    public function test_the_feed_can_show_only_recently_posted_tasks(): void
    {
        $this->task(['title' => 'Satu jam lalu'])
            ->forceFill(['created_at' => now()->subHour()])->save();
        $this->task(['title' => 'Tiga hari lalu'])
            ->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->assertSame(['Satu jam lalu'], $this->feed(['posted_within_hours' => 24]));
        $this->assertCount(2, $this->feed());
    }

    public function test_the_time_filter_is_validated(): void
    {
        foreach ([0, -5, 721, 'kemarin'] as $invalid) {
            $this->asUser($this->seeker)
                ->getJson(route('v1.tasks.index', ['posted_within_hours' => $invalid]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['posted_within_hours']);
        }
    }

    // ── nama pekerjaan ──────────────────────────────────────────────────────

    /**
     * Kata dua huruf. Tanpa penanganan khusus ini mustahil: ambang
     * `innodb_ft_min_token_size` bawaan MySQL adalah 3, jadi "AC" tidak pernah
     * masuk indeks — padahal "cuci AC" pencarian yang wajar di aplikasi ini.
     */
    public function test_searching_a_two_letter_name_works(): void
    {
        $this->task(['title' => 'Cuci AC dua unit']);
        $this->task(['title' => 'Cuci karpet']);

        $this->assertSame(['Cuci AC dua unit'], $this->feed(['q' => 'ac']));
    }

    /** Yang diketik akar katanya, yang tertulis bentuk berimbuhan. */
    public function test_searching_a_root_word_finds_the_affixed_title(): void
    {
        $this->task(['title' => 'Membersihkan gudang']);
        $this->task(['title' => 'Antar paket']);

        $this->assertSame(['Membersihkan gudang'], $this->feed(['q' => 'bersih']));
        $this->assertSame(['Membersihkan gudang'], $this->feed(['q' => 'bersihkan']));
    }

    /** Mengetik separuh kata sudah menemukan — terasa seperti mengetik. */
    public function test_a_partial_last_word_already_matches(): void
    {
        $this->task(['title' => 'Bersihkan rumah dua lantai']);
        $this->task(['title' => 'Antar paket']);

        $this->assertSame(['Bersihkan rumah dua lantai'], $this->feed(['q' => 'bersih rum']));
    }

    /**
     * Karakter operator BOOLEAN MODE tidak boleh sampai ke MySQL: di sana
     * `+ - ( ) " *` punya arti, dan kombinasi yang salah membuat MySQL
     * melempar galat sintaks — 500, bukan nol hasil.
     */
    public function test_a_keyword_full_of_operators_does_not_break_the_query(): void
    {
        $this->task(['title' => 'Cuci karpet']);

        $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['q' => 'cuci -"karpet" +(besar)']))
            ->assertOk();
    }

    public function test_the_feed_excludes_my_own_tasks(): void
    {
        $this->task(['title' => 'Orang lain']);
        $this->task(['title' => 'Punya saya', 'poster_id' => $this->seeker->getKey()]);

        $this->assertSame(['Orang lain'], $this->feed());
    }

    public function test_the_feed_excludes_closed_bidding(): void
    {
        $this->task(['title' => 'Buka']);
        $this->task(['title' => 'Tutup', 'bidding_closes_at' => now()->subDay()]);

        $this->assertSame(['Buka'], $this->feed());
    }

    public function test_the_feed_marks_my_bid(): void
    {
        $task = $this->task(['title' => 'Sudah ditawar']);
        Bid::factory()->create(['task_id' => $task->getKey(), 'bidder_id' => $this->seeker->getKey()]);

        $response = $this->asUser($this->seeker)->getJson(route('v1.tasks.index'))->assertOk();

        $this->assertNotNull($response->json('data.0.my_bid'));
        $this->assertSame('pending', $response->json('data.0.my_bid.status'));
    }

    public function test_exclude_my_bids_hides_them(): void
    {
        $task = $this->task(['title' => 'Sudah ditawar']);
        Bid::factory()->create(['task_id' => $task->getKey(), 'bidder_id' => $this->seeker->getKey()]);
        $this->task(['title' => 'Belum ditawar']);

        $this->assertSame(['Belum ditawar'], $this->feed(['exclude_my_bids' => 1]));
    }

    // ── kata kunci ──────────────────────────────────────────────────────────

    public function test_keyword_search_over_http(): void
    {
        $this->task(['title' => 'Cuci AC dua unit', 'description' => 'Isi freon.']);
        $this->task(['title' => 'Setrika pakaian', 'description' => 'Satu keranjang.']);

        $this->assertSame(['Cuci AC dua unit'], $this->feed(['q' => 'cuci']));
        $this->assertSame(['Cuci AC dua unit'], $this->feed(['q' => 'freon']));
        $this->assertSame(['Setrika pakaian'], $this->feed(['q' => 'setri']));
    }

    public function test_keyword_with_only_punctuation_returns_nothing(): void
    {
        $this->task(['title' => 'Ada isinya']);

        $this->assertSame([], $this->feed(['q' => '!!!']));
    }

    public function test_keyword_operators_do_not_cause_errors(): void
    {
        $this->task(['title' => 'Cuci AC']);

        foreach (['+cuci -ac', 'cuci*', '"cuci"', 'cuci~ac', '(cuci)'] as $raw) {
            $this->asUser($this->seeker)
                ->getJson(route('v1.tasks.index', ['q' => $raw]))
                ->assertOk();
        }
    }

    public function test_keyword_is_length_validated(): void
    {
        $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['q' => 'a']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['q']);
    }

    // ── jarak ───────────────────────────────────────────────────────────────

    public function test_radius_filter_over_http(): void
    {
        $this->task(['title' => 'Dekat', 'latitude' => -6.1754, 'longitude' => 106.8272]);
        $this->task(['title' => 'Jauh', 'latitude' => -6.9175, 'longitude' => 107.6191]);

        $this->assertSame(['Dekat'], $this->feed([
            'lat' => -6.1754, 'lng' => 106.8272, 'radius_km' => 5,
        ]));
    }

    public function test_the_feed_reports_the_distance(): void
    {
        $this->task(['title' => 'Dekat', 'latitude' => -6.1800, 'longitude' => 106.8300]);

        $distance = $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['lat' => -6.1754, 'lng' => 106.8272, 'radius_km' => 5]))
            ->assertOk()
            ->json('data.0.distance_km');

        $this->assertNotNull($distance);
        $this->assertLessThan(5, $distance);
    }

    public function test_distance_is_absent_without_coordinates(): void
    {
        $this->task(['title' => 'Tanpa filter jarak', 'latitude' => -6.18, 'longitude' => 106.83]);

        $item = $this->asUser($this->seeker)->getJson(route('v1.tasks.index'))->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('distance_km', $item);
    }

    /** lat & lng wajib berpasangan — bukan diabaikan diam-diam. */
    public function test_lat_without_lng_is_rejected(): void
    {
        $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['lat' => -6.1754]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lng']);
    }

    public function test_lng_without_lat_is_rejected(): void
    {
        $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['lng' => 106.8272]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lat']);
    }

    public function test_radius_above_the_maximum_is_rejected(): void
    {
        $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['lat' => -6.1754, 'lng' => 106.8272, 'radius_km' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['radius_km']);
    }

    public function test_out_of_range_coordinates_are_rejected(): void
    {
        $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['lat' => 200, 'lng' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    // ── keahlian ────────────────────────────────────────────────────────────

    public function test_skills_filter_accepts_a_comma_separated_list(): void
    {
        $this->task(['title' => 'Cuci AC'], ['cuci-ac']);
        $this->task(['title' => 'Setrika'], ['setrika']);
        $this->task(['title' => 'Jaga kucing'], ['jaga-kucing']);

        $this->assertEqualsCanonicalizing(
            ['Cuci AC', 'Setrika'],
            $this->feed(['skills' => 'cuci-ac,setrika']),
        );
    }

    public function test_skills_filter_accepts_an_array(): void
    {
        $this->task(['title' => 'Cuci AC'], ['cuci-ac']);
        $this->task(['title' => 'Setrika'], ['setrika']);

        $this->assertSame(['Cuci AC'], $this->feed(['skills' => ['cuci-ac']]));
    }

    public function test_match_my_skills_uses_the_profile(): void
    {
        $this->seeker->skills()->sync(Skill::query()->whereIn('slug', ['cuci-ac'])->pluck('id'));
        $this->task(['title' => 'Cuci AC'], ['cuci-ac']);
        $this->task(['title' => 'Setrika'], ['setrika']);

        $this->assertSame(['Cuci AC'], $this->feed(['match_my_skills' => 1]));
    }

    // ── filter lain ─────────────────────────────────────────────────────────

    public function test_city_and_category_filters(): void
    {
        // Kategori disebut EKSPLISIT untuk keduanya. anyCategory() mengembalikan
        // kategori pertama (mencuci), jadi mengandalkannya akan menaruh kedua
        // task di kategori yang sama dan filternya tampak tidak bekerja.
        $mencuci = Category::query()->where('slug', 'mencuci')->firstOrFail();
        $jagaHewan = Category::query()->where('slug', 'jaga-hewan')->firstOrFail();
        $this->task(['title' => 'Jakarta mencuci', 'city' => 'Jakarta', 'category_id' => $mencuci->getKey()]);
        $this->task(['title' => 'Bandung', 'city' => 'Bandung', 'category_id' => $jagaHewan->getKey()]);

        $this->assertSame(['Jakarta mencuci'], $this->feed(['city' => 'Jakarta']));
        $this->assertSame(['Jakarta mencuci'], $this->feed(['category_id' => $mencuci->getKey()]));
    }

    public function test_budget_range_filter(): void
    {
        $this->task(['title' => 'Murah', 'budget_min' => 50_000]);
        $this->task(['title' => 'Mahal', 'budget_min' => 900_000]);

        $this->assertSame(['Murah'], $this->feed(['budget_from' => 10_000, 'budget_to' => 100_000]));
    }

    public function test_budget_to_below_from_is_rejected(): void
    {
        $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['budget_from' => 100, 'budget_to' => 50]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['budget_to']);
    }

    public function test_all_filters_combine_over_http(): void
    {
        $this->seeker->skills()->sync(Skill::query()->whereIn('slug', ['cuci-ac'])->pluck('id'));
        $this->task([
            'title' => 'Cuci AC dekat', 'city' => 'Jakarta',
            'latitude' => -6.1754, 'longitude' => 106.8272,
        ], ['cuci-ac']);
        $this->task([
            'title' => 'Cuci AC jauh', 'city' => 'Bandung',
            'latitude' => -6.9175, 'longitude' => 107.6191,
        ], ['cuci-ac']);

        $this->assertSame(['Cuci AC dekat'], $this->feed([
            'q' => 'cuci', 'match_my_skills' => 1,
            'lat' => -6.1754, 'lng' => 106.8272, 'radius_km' => 5,
        ]));
    }

    // ── pagination ──────────────────────────────────────────────────────────

    public function test_cursor_meta_shape(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->task(['title' => "Task {$i}"]);
        }

        $meta = $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['per_page' => 1]))
            ->assertOk()
            ->json('meta');

        $this->assertArrayHasKey('next_cursor', $meta);
        $this->assertArrayHasKey('prev_cursor', $meta);
        $this->assertArrayNotHasKey('current_page', $meta);
        $this->assertArrayNotHasKey('total', $meta);
        $this->assertArrayNotHasKey('last_page', $meta);
    }

    public function test_cursor_pages_do_not_overlap_over_http(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->task(['title' => "Task {$i}"]);
        }

        $first = $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['per_page' => 2]))->assertOk();
        $second = $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['per_page' => 2, 'cursor' => $first->json('meta.next_cursor')]))
            ->assertOk();

        $this->assertSame([], array_intersect(
            $first->json('data.*.id'),
            $second->json('data.*.id'),
        ));
    }

    public function test_page_parameter_is_ignored(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->task(['title' => "Task {$i}"]);
        }

        $a = $this->asUser($this->seeker)->getJson(route('v1.tasks.index', ['per_page' => 1]))->json('data.0.id');
        $b = $this->asUser($this->seeker)->getJson(route('v1.tasks.index', ['per_page' => 1, 'page' => 3]))->json('data.0.id');

        $this->assertSame($a, $b);
    }

    public function test_per_page_above_maximum_is_rejected(): void
    {
        $this->asUser($this->seeker)
            ->getJson(route('v1.tasks.index', ['per_page' => 5000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_default_order_is_newest_first_over_http(): void
    {
        $this->task(['title' => 'Lama', 'created_at' => now()->subDays(2)]);
        $this->task(['title' => 'Baru', 'created_at' => now()]);

        $this->assertSame(['Baru', 'Lama'], $this->feed());
    }
}
