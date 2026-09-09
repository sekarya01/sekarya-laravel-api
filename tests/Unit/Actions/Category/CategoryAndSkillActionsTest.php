<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Category;

use App\Actions\Category\ListCategoriesAction;
use App\Actions\Category\RecomputeReferencePricesAction;
use App\Actions\Skill\ListSkillsAction;
use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Skill;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CategoryAndSkillActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    public function test_it_lists_active_categories_in_order(): void
    {
        $categories = app(ListCategoriesAction::class)->handle();

        $this->assertGreaterThanOrEqual(9, $categories->count());
        $this->assertSame(
            $categories->pluck('sort_order')->sort()->values()->all(),
            $categories->pluck('sort_order')->values()->all(),
        );
    }

    public function test_inactive_categories_are_hidden(): void
    {
        Category::query()->firstOrFail()->forceFill(['is_active' => false])->save();

        $slugs = app(ListCategoriesAction::class)->handle()->pluck('is_active')->unique();

        $this->assertSame([true], $slugs->all());
    }

    public function test_it_lists_skills(): void
    {
        $skills = app(ListSkillsAction::class)->handle();

        $this->assertGreaterThanOrEqual(40, $skills->count());
    }

    public function test_skills_can_be_filtered_by_category(): void
    {
        $category = Category::query()->where('slug', 'bersih-rumah')->firstOrFail();

        $skills = app(ListSkillsAction::class)->handle($category->getKey());

        $this->assertGreaterThan(0, $skills->count());
        $this->assertSame([$category->getKey()], $skills->pluck('category_id')->unique()->all());
    }

    public function test_inactive_skills_are_hidden(): void
    {
        Skill::query()->firstOrFail()->forceFill(['is_active' => false])->save();

        $this->assertSame([true], app(ListSkillsAction::class)->handle()->pluck('is_active')->unique()->all());
    }

    // ── harga referensi ─────────────────────────────────────────────────────

    /**
     * Task selesai dengan pekerja yang diterima, di kategori tersendiri.
     *
     * Kategori dibuat khusus per test, bukan `anyCategory()`: kelas test yang
     * memakai DatabaseTruncation meninggalkan baris ter-commit, dan
     * RecomputeReferencePrices menghitung SELURUH baris kategori itu — dengan
     * kategori bersama, angka yang keluar bukan angka yang test ini siapkan.
     *
     * @param  list<int>  $amounts  harga per orang, satu pekerja per task
     */
    private function completedTasksWorth(Category $category, array $amounts): void
    {
        $poster = $this->activeUser();

        foreach ($amounts as $amount) {
            $task = Task::factory()->create([
                'poster_id' => $poster->getKey(),
                'category_id' => $category->getKey(),
                'status' => TaskStatus::Completed,
                'agreed_amount' => $amount,
                'budget_min' => 1,
            ]);

            // Sumber harga referensi adalah penawaran yang DITERIMA, bukan
            // `tasks.agreed_amount` — sejak satu task bisa merekrut banyak
            // orang, kolom itu berarti total seluruh pekerja.
            Bid::factory()->create([
                'task_id' => $task->getKey(),
                'bidder_id' => $this->activeUser()->getKey(),
                'amount' => $amount,
                'status' => BidStatus::Accepted,
            ]);
        }
    }

    /** Sumbernya agreed_amount task SELESAI, bukan budget maupun penawaran. */
    public function test_it_computes_reference_prices_from_completed_tasks(): void
    {
        $category = Category::factory()->create();
        $this->completedTasksWorth($category, [100_000, 200_000, 300_000]);

        $updated = app(RecomputeReferencePricesAction::class)->handle();

        $category->refresh();
        $this->assertGreaterThanOrEqual(1, $updated);
        $this->assertSame(100_000, $category->ref_price_min);
        $this->assertSame(300_000, $category->ref_price_max);
        $this->assertSame(200_000, $category->ref_price_median);
        $this->assertSame(3, $category->ref_sample_size);
        $this->assertNotNull($category->ref_computed_at);
        $this->assertTrue($category->hasRealPriceData());
    }

    /** Median, bukan rata-rata: satu task mahal tidak boleh merusak angkanya. */
    public function test_it_uses_the_median_not_the_mean(): void
    {
        $category = Category::factory()->create();
        $this->completedTasksWorth($category, [100_000, 100_000, 100_000, 5_000_000]);

        app(RecomputeReferencePricesAction::class)->handle();

        // rata-rata = 1.325.000, median = 100.000
        $this->assertSame(100_000, $category->refresh()->ref_price_median);
    }

    public function test_even_sample_size_averages_the_two_middle_values(): void
    {
        $category = Category::factory()->create();
        $this->completedTasksWorth($category, [100_000, 200_000]);

        app(RecomputeReferencePricesAction::class)->handle();

        $this->assertSame(150_000, $category->refresh()->ref_price_median);
    }

    public function test_unfinished_tasks_are_ignored(): void
    {
        $category = $this->anyCategory();
        $poster = $this->activeUser();
        Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $category->getKey(),
            'status' => TaskStatus::Open,
            'agreed_amount' => 999_999,
            'budget_min' => 1,
        ]);

        $before = $category->ref_sample_size;
        app(RecomputeReferencePricesAction::class)->handle();

        $this->assertSame($before, $category->refresh()->ref_sample_size);
    }

    /** Nilai seed manual harus tetap ditandai bukan data nyata. */
    public function test_seeded_values_report_sample_size_zero(): void
    {
        $category = Category::query()->where('slug', 'bersih-rumah')->firstOrFail();

        $this->assertSame(0, $category->ref_sample_size);
        $this->assertFalse($category->hasRealPriceData());
        $this->assertNotNull($category->ref_price_median);
    }
}
