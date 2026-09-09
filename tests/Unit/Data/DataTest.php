<?php

declare(strict_types=1);

namespace Tests\Unit\Data;

use App\Data\CursorPageData;
use App\Data\Task\ListTasksData;
use App\Data\User\UpdateProfileData;
use App\Enums\UserActiveMode;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

final class DataTest extends TestCase
{
    // ── CursorPageData ──────────────────────────────────────────────────────

    public function test_it_uses_the_default_page_size(): void
    {
        $this->assertSame(20, (new CursorPageData)->perPage);
        $this->assertSame(20, CursorPageData::DEFAULT_PER_PAGE);
        $this->assertSame(50, CursorPageData::MAX_PER_PAGE);
    }

    public function test_it_clamps_a_page_size_above_the_maximum(): void
    {
        $page = CursorPageData::fromRequest(new Request(['per_page' => 5000]));

        $this->assertSame(50, $page->perPage);
    }

    /** Clamping ada di DTO juga supaya aman saat dipakai di luar HTTP. */
    public function test_it_clamps_a_zero_or_negative_page_size(): void
    {
        $this->assertSame(1, CursorPageData::fromRequest(new Request(['per_page' => 0]))->perPage);
        $this->assertSame(1, CursorPageData::fromRequest(new Request(['per_page' => -10]))->perPage);
    }

    public function test_it_accepts_a_page_size_inside_the_range(): void
    {
        $this->assertSame(7, CursorPageData::fromRequest(new Request(['per_page' => 7]))->perPage);
    }

    public function test_it_publishes_shared_validation_rules(): void
    {
        $rules = CursorPageData::rules();

        $this->assertArrayHasKey('per_page', $rules);
        $this->assertArrayHasKey('cursor', $rules);
        $this->assertContains('max:50', $rules['per_page']);
    }

    // ── ListTasksData ───────────────────────────────────────────────────────

    public function test_coordinates_require_both_values(): void
    {
        $page = new CursorPageData;

        $this->assertFalse((new ListTasksData($page))->hasCoordinates());
        $this->assertFalse((new ListTasksData($page, latitude: -6.1))->hasCoordinates());
        $this->assertFalse((new ListTasksData($page, longitude: 106.8))->hasCoordinates());
        $this->assertTrue((new ListTasksData($page, latitude: -6.1, longitude: 106.8))->hasCoordinates());
    }

    public function test_radius_bounds(): void
    {
        $this->assertSame(10.0, ListTasksData::DEFAULT_RADIUS_KM);
        $this->assertSame(100.0, ListTasksData::MAX_RADIUS_KM);
    }

    // ── UpdateProfileData ───────────────────────────────────────────────────

    /** Field yang tidak dikirim tidak boleh menimpa kolom dengan null. */
    public function test_to_attributes_only_includes_what_was_set(): void
    {
        $data = new UpdateProfileData(name: 'Budi', city: 'Jakarta');

        $this->assertSame(['name' => 'Budi', 'city' => 'Jakarta'], $data->toAttributes());
    }

    public function test_to_attributes_is_empty_when_nothing_set(): void
    {
        $this->assertSame([], (new UpdateProfileData)->toAttributes());
    }

    /** Skills bukan kolom, jadi tidak boleh ikut ke toAttributes(). */
    public function test_skills_are_excluded_from_column_attributes(): void
    {
        $data = new UpdateProfileData(name: 'Budi', skills: ['cuci-ac']);

        $this->assertArrayNotHasKey('skills', $data->toAttributes());
        $this->assertSame(['cuci-ac'], $data->skills);
    }

    public function test_active_mode_is_passed_through(): void
    {
        $data = new UpdateProfileData(activeMode: UserActiveMode::Working);

        $this->assertSame(UserActiveMode::Working, $data->toAttributes()['active_mode']);
    }
}
