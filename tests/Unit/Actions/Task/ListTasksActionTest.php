<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Task;

use App\Actions\Task\ListTasksAction;
use App\Data\CursorPageData;
use App\Data\Task\ListTasksData;
use App\Enums\TaskStatus;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Skill;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

/**
 * Feed pencari kerja beserta seluruh filternya.
 *
 * Berjalan di MySQL karena memakai MATCH ... AGAINST dan LEAST/GREATEST —
 * keduanya bukan sintaks SQLite yang sah.
 *
 * DatabaseTruncation, BUKAN RefreshDatabase. Ini bukan preferensi:
 *
 *   Indeks FULLTEXT InnoDB tidak diperbarui sampai transaksi COMMIT.
 *   RefreshDatabase membungkus setiap test dalam satu transaksi lalu
 *   me-rollback-nya, sehingga baris yang dimasukkan di dalam test itu
 *   TIDAK PERNAH terlihat oleh `MATCH ... AGAINST`. Akibatnya seluruh test
 *   pencarian kata kunci mengembalikan nol baris dan tampak seperti bug
 *   aplikasi, padahal aplikasinya benar.
 *
 *   DatabaseTruncation meng-commit lalu membersihkan tabel, jadi indeksnya
 *   terbentuk. Lebih lambat, tapi ini satu-satunya cara menguji FULLTEXT.
 */
final class ListTasksActionTest extends TestCase
{
    use DatabaseTruncation;

    private User $seeker;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->seeker = $this->activeUser();
        $this->other = $this->activeUser();
    }

    private function task(array $attributes = [], array $skills = []): Task
    {
        $task = Task::factory()->create([
            'poster_id' => $this->other->getKey(),
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

    private function data(array $override = []): ListTasksData
    {
        return new ListTasksData(
            page: new CursorPageData($override['perPage'] ?? 20),
            status: $override['status'] ?? null,
            categoryId: $override['categoryId'] ?? null,
            city: $override['city'] ?? null,
            budgetFrom: $override['budgetFrom'] ?? null,
            budgetTo: $override['budgetTo'] ?? null,
            excludeMyBids: $override['excludeMyBids'] ?? false,
            postedWithinHours: $override['postedWithinHours'] ?? null,
            keyword: $override['keyword'] ?? null,
            latitude: $override['latitude'] ?? null,
            longitude: $override['longitude'] ?? null,
            radiusKm: $override['radiusKm'] ?? 10.0,
            skillSlugs: $override['skillSlugs'] ?? [],
            matchMySkills: $override['matchMySkills'] ?? false,
        );
    }

    private function open(array $override = []): array
    {
        return app(ListTasksAction::class)
            ->open($this->data($override), $this->seeker)
            ->pluck('title')
            ->all();
    }

    // ── tiga pengecualian yang wajib ────────────────────────────────────────

    public function test_it_lists_open_tasks_from_others(): void
    {
        $this->task(['title' => 'Punya orang lain']);

        $this->assertSame(['Punya orang lain'], $this->open());
    }

    public function test_it_excludes_my_own_tasks(): void
    {
        $this->task(['title' => 'Punya orang lain']);
        $this->task(['title' => 'Punya saya', 'poster_id' => $this->seeker->getKey()]);

        $this->assertSame(['Punya orang lain'], $this->open());
    }

    public function test_it_excludes_drafts(): void
    {
        $this->task(['title' => 'Terbuka']);
        $this->task(['title' => 'Draft', 'status' => TaskStatus::Draft]);

        $this->assertSame(['Terbuka'], $this->open());
    }

    /** Status open saja tidak cukup — masa lelang bisa sudah lewat. */
    public function test_it_excludes_tasks_whose_bidding_window_closed(): void
    {
        $this->task(['title' => 'Masih buka']);
        $this->task(['title' => 'Sudah tutup', 'bidding_closes_at' => now()->subDay()]);

        $this->assertSame(['Masih buka'], $this->open());
    }

    public function test_a_future_bidding_window_is_included(): void
    {
        $this->task(['title' => 'Tutup besok', 'bidding_closes_at' => now()->addDay()]);

        $this->assertSame(['Tutup besok'], $this->open());
    }

    public function test_it_marks_tasks_i_already_bid_on(): void
    {
        $task = $this->task(['title' => 'Sudah saya tawar']);
        Bid::factory()->create([
            'task_id' => $task->getKey(),
            'bidder_id' => $this->seeker->getKey(),
        ]);

        $page = app(ListTasksAction::class)->open($this->data(), $this->seeker);

        $this->assertNotNull($page->first()->myBid, 'my_bid harus terisi');
        $this->assertSame($this->seeker->getKey(), (int) $page->first()->myBid->bidder_id);
    }

    public function test_my_bid_is_null_for_untouched_tasks(): void
    {
        $this->task(['title' => 'Belum ditawar']);

        $page = app(ListTasksAction::class)->open($this->data(), $this->seeker);

        $this->assertNull($page->first()->myBid);
    }

    public function test_exclude_my_bids_hides_them(): void
    {
        $task = $this->task(['title' => 'Sudah saya tawar']);
        Bid::factory()->create([
            'task_id' => $task->getKey(),
            'bidder_id' => $this->seeker->getKey(),
        ]);
        $this->task(['title' => 'Belum ditawar']);

        $this->assertSame(['Belum ditawar'], $this->open(['excludeMyBids' => true]));
    }

    // ── urutan ──────────────────────────────────────────────────────────────

    public function test_default_order_is_newest_first(): void
    {
        $this->task(['title' => 'Lama', 'created_at' => now()->subDays(2)]);
        $this->task(['title' => 'Baru', 'created_at' => now()]);

        $this->assertSame(['Baru', 'Lama'], $this->open());
    }

    // ── kata kunci (FULLTEXT) ───────────────────────────────────────────────

    // ── waktu: seberapa baru task diposting ─────────────────────────────────

    /**
     * Filter yang dipakai pencari kerja yang memantau feed sepanjang hari:
     * "tampilkan yang masuk sejak tadi pagi", supaya ia tidak melihat task
     * yang sama berulang kali.
     */
    public function test_posted_within_hours_hides_older_tasks(): void
    {
        $this->task(['title' => 'Baru saja'])
            ->forceFill(['created_at' => now()->subHour()])->save();
        $this->task(['title' => 'Kemarin'])
            ->forceFill(['created_at' => now()->subHours(30)])->save();
        $this->task(['title' => 'Minggu lalu'])
            ->forceFill(['created_at' => now()->subDays(8)])->save();

        $this->assertSame(['Baru saja'], $this->open(['postedWithinHours' => 24]));
    }

    public function test_the_time_filter_is_off_by_default(): void
    {
        $this->task(['title' => 'Lama'])
            ->forceFill(['created_at' => now()->subDays(60)])->save();

        $this->assertSame(['Lama'], $this->open());
    }

    /** Batasnya dihitung dari jam server, dan inklusif di tepi. */
    public function test_a_task_right_at_the_boundary_is_kept(): void
    {
        $this->task(['title' => 'Tepat di batas'])
            ->forceFill(['created_at' => now()->subHours(24)->addSeconds(5)])->save();

        $this->assertSame(['Tepat di batas'], $this->open(['postedWithinHours' => 24]));
    }

    public function test_the_time_filter_combines_with_the_others(): void
    {
        $this->task(['title' => 'Baru & cocok', 'city' => 'Jakarta'])
            ->forceFill(['created_at' => now()->subHour()])->save();
        $this->task(['title' => 'Baru, kota lain', 'city' => 'Bandung'])
            ->forceFill(['created_at' => now()->subHour()])->save();
        $this->task(['title' => 'Lama & cocok', 'city' => 'Jakarta'])
            ->forceFill(['created_at' => now()->subDays(3)])->save();

        $this->assertSame(
            ['Baru & cocok'],
            $this->open(['postedWithinHours' => 24, 'city' => 'Jakarta']),
        );
    }

    // ── nama pekerjaan ──────────────────────────────────────────────────────

    public function test_keyword_matches_the_title(): void
    {
        $this->task(['title' => 'Cuci AC dua unit', 'description' => 'Servis rutin.']);
        $this->task(['title' => 'Setrika pakaian', 'description' => 'Satu keranjang.']);

        $this->assertSame(['Cuci AC dua unit'], $this->open(['keyword' => 'cuci']));
    }

    public function test_keyword_matches_the_description(): void
    {
        $this->task(['title' => 'Servis rumah', 'description' => 'Butuh isi freon evaporator.']);
        $this->task(['title' => 'Setrika', 'description' => 'Satu keranjang penuh.']);

        $this->assertSame(['Servis rumah'], $this->open(['keyword' => 'freon']));
    }

    /** Kata dua huruf — mustahil ditemukan tanpa penanganan khusus. */
    public function test_keyword_finds_a_two_letter_word(): void
    {
        $this->task(['title' => 'Cuci AC dua unit', 'description' => 'Servis rutin.']);
        $this->task(['title' => 'Setrika pakaian', 'description' => 'Satu keranjang.']);

        $this->assertSame(['Cuci AC dua unit'], $this->open(['keyword' => 'ac']));
    }

    /** Yang diketik akar katanya, yang tertulis bentuk berimbuhan. */
    public function test_keyword_matches_across_indonesian_affixes(): void
    {
        $this->task(['title' => 'Membersihkan gudang', 'description' => 'Dua lantai.']);
        $this->task(['title' => 'Antar paket', 'description' => 'Ke kantor pos.']);

        $this->assertSame(['Membersihkan gudang'], $this->open(['keyword' => 'bersih']));
    }

    public function test_multiple_keywords_are_combined_with_and(): void
    {
        $this->task(['title' => 'Bersihkan kamar mandi', 'description' => 'Berkerak.']);
        $this->task(['title' => 'Bersihkan dapur', 'description' => 'Berminyak.']);

        $this->assertSame(['Bersihkan kamar mandi'], $this->open(['keyword' => 'kamar mandi']));
    }

    public function test_the_last_keyword_matches_as_a_prefix(): void
    {
        $this->task(['title' => 'Setrika pakaian menumpuk', 'description' => 'Rapi.']);

        $this->assertSame(['Setrika pakaian menumpuk'], $this->open(['keyword' => 'setri']));
    }

    /** Masukan tanda baca saja tidak boleh mengembalikan seluruh data. */
    public function test_punctuation_only_keyword_returns_nothing(): void
    {
        $this->task(['title' => 'Ada isinya']);

        $this->assertSame([], $this->open(['keyword' => '!!!']));
    }

    public function test_unmatched_keyword_returns_nothing(): void
    {
        $this->task(['title' => 'Cuci AC']);

        $this->assertSame([], $this->open(['keyword' => 'zebra']));
    }

    // ── jarak ───────────────────────────────────────────────────────────────

    public function test_radius_filters_by_distance(): void
    {
        // Monas
        $this->task(['title' => 'Dekat', 'latitude' => -6.1754, 'longitude' => 106.8272]);
        // Bandung, ~120 km
        $this->task(['title' => 'Jauh', 'latitude' => -6.9175, 'longitude' => 107.6191]);

        $this->assertSame(['Dekat'], $this->open([
            'latitude' => -6.1754, 'longitude' => 106.8272, 'radiusKm' => 5.0,
        ]));
    }

    /** Regresi: float yang di-bind harus dibandingkan sebagai angka. */
    public function test_a_wider_radius_includes_the_far_task(): void
    {
        $this->task(['title' => 'Dekat', 'latitude' => -6.1754, 'longitude' => 106.8272]);
        $this->task(['title' => 'Jauh', 'latitude' => -6.9175, 'longitude' => 107.6191]);

        $titles = $this->open([
            'latitude' => -6.1754, 'longitude' => 106.8272, 'radiusKm' => 200.0,
        ]);

        $this->assertEqualsCanonicalizing(['Dekat', 'Jauh'], $titles);
    }

    public function test_it_reports_the_distance(): void
    {
        $this->task(['title' => 'Dekat', 'latitude' => -6.1800, 'longitude' => 106.8300]);

        $page = app(ListTasksAction::class)->open($this->data([
            'latitude' => -6.1754, 'longitude' => 106.8272, 'radiusKm' => 5.0,
        ]), $this->seeker);

        $this->assertNotNull($page->first()->distance_km);
        $this->assertLessThan(5.0, (float) $page->first()->distance_km);
    }

    public function test_tasks_without_coordinates_are_excluded_from_radius_search(): void
    {
        $this->task(['title' => 'Tanpa koordinat', 'latitude' => null, 'longitude' => null]);

        $this->assertSame([], $this->open([
            'latitude' => -6.1754, 'longitude' => 106.8272, 'radiusKm' => 50.0,
        ]));
    }

    // ── keahlian ────────────────────────────────────────────────────────────

    public function test_it_filters_by_skill_slug(): void
    {
        $this->task(['title' => 'Butuh cuci AC'], ['cuci-ac']);
        $this->task(['title' => 'Butuh setrika'], ['setrika']);

        $this->assertSame(['Butuh cuci AC'], $this->open(['skillSlugs' => ['cuci-ac']]));
    }

    public function test_multiple_skills_match_any_of_them(): void
    {
        $this->task(['title' => 'Cuci AC'], ['cuci-ac']);
        $this->task(['title' => 'Setrika'], ['setrika']);
        $this->task(['title' => 'Jaga kucing'], ['jaga-kucing']);

        $this->assertEqualsCanonicalizing(
            ['Cuci AC', 'Setrika'],
            $this->open(['skillSlugs' => ['cuci-ac', 'setrika']]),
        );
    }

    public function test_match_my_skills_uses_my_profile(): void
    {
        $this->seeker->skills()->sync(Skill::query()->whereIn('slug', ['cuci-ac'])->pluck('id'));
        $this->task(['title' => 'Cuci AC'], ['cuci-ac']);
        $this->task(['title' => 'Setrika'], ['setrika']);

        $this->assertSame(['Cuci AC'], $this->open(['matchMySkills' => true]));
    }

    public function test_match_my_skills_returns_nothing_without_skills(): void
    {
        $this->task(['title' => 'Cuci AC'], ['cuci-ac']);

        $this->assertSame([], $this->open(['matchMySkills' => true]));
    }

    public function test_explicit_skills_take_precedence_over_match_my_skills(): void
    {
        $this->seeker->skills()->sync(Skill::query()->whereIn('slug', ['setrika'])->pluck('id'));
        $this->task(['title' => 'Cuci AC'], ['cuci-ac']);

        $this->assertSame(
            ['Cuci AC'],
            $this->open(['skillSlugs' => ['cuci-ac'], 'matchMySkills' => true]),
        );
    }

    // ── filter lain ─────────────────────────────────────────────────────────

    public function test_it_filters_by_city(): void
    {
        $this->task(['title' => 'Jakarta', 'city' => 'Jakarta']);
        $this->task(['title' => 'Bandung', 'city' => 'Bandung']);

        $this->assertSame(['Jakarta'], $this->open(['city' => 'Jakarta']));
    }

    public function test_it_filters_by_category(): void
    {
        $a = Category::query()->where('slug', 'mencuci')->firstOrFail();
        $b = Category::query()->where('slug', 'jaga-hewan')->firstOrFail();
        $this->task(['title' => 'Mencuci', 'category_id' => $a->getKey()]);
        $this->task(['title' => 'Jaga hewan', 'category_id' => $b->getKey()]);

        $this->assertSame(['Mencuci'], $this->open(['categoryId' => $a->getKey()]));
    }

    public function test_it_filters_by_budget_range(): void
    {
        $this->task(['title' => 'Murah', 'budget_min' => 50_000]);
        $this->task(['title' => 'Mahal', 'budget_min' => 900_000]);

        $this->assertSame(['Murah'], $this->open(['budgetFrom' => 10_000, 'budgetTo' => 100_000]));
    }

    public function test_all_filters_combine(): void
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

        $this->assertSame(['Cuci AC dekat'], $this->open([
            'keyword' => 'cuci', 'matchMySkills' => true,
            'latitude' => -6.1754, 'longitude' => 106.8272, 'radiusKm' => 5.0,
        ]));
    }

    // ── daftar milik sendiri ────────────────────────────────────────────────

    public function test_posted_by_lists_only_my_tasks(): void
    {
        $this->task(['title' => 'Orang lain']);
        Task::factory()->create([
            'poster_id' => $this->seeker->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Punya saya',
            'status' => TaskStatus::Draft,
        ]);

        $titles = app(ListTasksAction::class)
            ->postedBy($this->data(), $this->seeker)->pluck('title')->all();

        $this->assertSame(['Punya saya'], $titles);
    }

    public function test_posted_by_can_filter_status(): void
    {
        foreach ([TaskStatus::Draft, TaskStatus::Open] as $status) {
            Task::factory()->create([
                'poster_id' => $this->seeker->getKey(),
                'category_id' => $this->anyCategory()->getKey(),
                'title' => $status->value,
                'status' => $status,
            ]);
        }

        $titles = app(ListTasksAction::class)
            ->postedBy($this->data(['status' => TaskStatus::Open]), $this->seeker)
            ->pluck('title')->all();

        $this->assertSame(['open'], $titles);
    }

    public function test_worked_by_lists_tasks_i_work_on(): void
    {
        $mine = Task::factory()->create([
            'poster_id' => $this->other->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Saya kerjakan',
            'status' => TaskStatus::Active,
        ]);
        $this->hireWorker($mine, $this->seeker);
        $this->task(['title' => 'Bukan saya']);

        $titles = app(ListTasksAction::class)
            ->workedBy($this->data(), $this->seeker)->pluck('title')->all();

        $this->assertSame(['Saya kerjakan'], $titles);
    }

    // ── pagination ──────────────────────────────────────────────────────────

    public function test_cursor_pages_do_not_overlap(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->task(['title' => "Task {$i}"]);
        }

        $first = app(ListTasksAction::class)->open($this->data(['perPage' => 2]), $this->seeker);
        $this->assertCount(2, $first->items());
        $this->assertNotNull($first->nextCursor());

        $second = app(ListTasksAction::class)
            ->open($this->data(['perPage' => 2]), $this->seeker)
            ->withPath('/')
            ->toArray();

        $this->assertCount(2, $second['data']);
    }
}
