<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Activity;

use App\Actions\Activity\ConfirmArrivalAction;
use App\Actions\Activity\DepartActivityAction;
use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Data\Activity\SubmitActivityData;
use App\Enums\ActivityStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Permintaan KEDUA dari dua yang bersamaan: model dari rute dimuat sebelum
 * permintaan pertama menyimpan transisinya. Pemeriksaan status wajib membaca
 * ulang baris di bawah kunci — kalau memakai salinan basi, transisi yang sama
 * lolos dua kali dan stempel waktu / foto bukti pertama tertimpa.
 */
final class StaleActivityTransitionTest extends TestCase
{
    use RefreshDatabase;

    /** Activity berstatus `$from` yang di basis data sudah berpindah ke `$now`. */
    private function staleActivity(ActivityStatus $from, ActivityStatus $now): Activity
    {
        $this->seedReference();
        $activity = Activity::factory()->create([
            'task_id' => Task::factory()->create([
                'category_id' => $this->anyCategory()->getKey(),
                'status' => TaskStatus::Active,
            ])->getKey(),
            'status' => $from,
        ]);
        $stale = Activity::query()->findOrFail($activity->getKey());

        Activity::query()->whereKey($activity->getKey())->update(['status' => $now]);

        return $stale;
    }

    public function test_depart_rejects_an_activity_that_already_departed(): void
    {
        $stale = $this->staleActivity(ActivityStatus::Open, ActivityStatus::OnTheWay);

        $this->expectException(InvalidStatusTransitionException::class);
        app(DepartActivityAction::class)->handle($stale);
    }

    public function test_arrival_is_not_confirmed_twice(): void
    {
        $stale = $this->staleActivity(ActivityStatus::OnTheWay, ActivityStatus::Arrived);

        $this->expectException(InvalidStatusTransitionException::class);
        app(ConfirmArrivalAction::class)->handle($stale);
    }

    public function test_start_rejects_an_activity_that_already_started(): void
    {
        $stale = $this->staleActivity(ActivityStatus::Arrived, ActivityStatus::InProgress);

        $this->expectException(InvalidStatusTransitionException::class);
        app(StartActivityAction::class)->handle($stale);
    }

    public function test_submit_does_not_overwrite_an_earlier_submission(): void
    {
        $stale = $this->staleActivity(ActivityStatus::InProgress, ActivityStatus::Submitted);

        $this->expectException(InvalidStatusTransitionException::class);
        app(SubmitActivityAction::class)->handle(new SubmitActivityData('kedua'), $stale);
    }
}
