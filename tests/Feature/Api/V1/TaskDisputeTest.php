<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Actions\Bid\AcceptBidAction;
use App\Actions\Bid\PlaceBidAction;
use App\Data\Activity\SubmitActivityData;
use App\Data\Bid\PlaceBidData;
use App\Enums\ActivityStatus;
use App\Enums\AdminAction;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\AdminAuditLog;
use App\Models\Task;
use App\Models\TaskDispute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Sengketa per mitra lewat HTTP: pemberi kerja mengajukan, mitra menanggapi
 * sekali, pengelola memutuskan dengan keterangan wajib.
 */
final class TaskDisputeTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    /** @var list<User> */
    private array $workers = [];

    private User $stranger;

    private Task $task;

    /** @var Collection<int, Activity> */
    private Collection $activities;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->stranger = $this->activeUser();
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
            'budget_min' => 100_000,
            'workers_needed' => 2,
        ]);

        foreach ([100_000, 150_000] as $amount) {
            $worker = $this->activeUser();
            $this->workers[] = $worker;
            $bid = app(PlaceBidAction::class)->handle(new PlaceBidData(amount: $amount, message: 'siap'), $this->task, $worker);
            app(AcceptBidAction::class)->handle($bid, $this->poster);
        }

        $this->activities = $this->openActivities($this->task->refresh(), $this->poster)->values()
            ->map(function (Activity $activity): Activity {
                app(StartActivityAction::class)->handle($this->bringToSite($activity));

                return app(SubmitActivityAction::class)
                    ->handle(new SubmitActivityData('beres', ['p/a.jpg']), $activity->refresh());
            });
    }

    private function raise(int $i, array $payload = []): TestResponse
    {
        return $this->asUser($this->poster)->postJson(
            route('v1.activities.disputes.store', $this->activities[$i]->ulid),
            $payload + ['category' => 'late', 'reason' => 'Datang tiga jam lewat jadwal.'],
        );
    }

    public function test_the_poster_disputes_one_worker_in_one_call(): void
    {
        $photos = $this->proofPhotosFor($this->poster);

        $this->raise(0, ['evidence_photos' => $photos])
            ->assertCreated()
            ->assertJsonPath('data.activity_id', $this->activities[0]->ulid)
            ->assertJsonPath('data.category', 'late')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.evidence_photos', $photos)
            ->assertJsonPath('data.worker_response', null);

        $this->assertSame(ActivityStatus::Rejected, $this->activities[0]->refresh()->status);
        $this->assertSame(ActivityStatus::Submitted, $this->activities[1]->refresh()->status);
        $this->assertSame(TaskStatus::Disputed, $this->task->refresh()->status);
    }

    public function test_description_and_category_are_required_photos_are_optional(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.activities.disputes.store', $this->activities[0]->ulid), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category', 'reason']);

        $this->raise(0, ['category' => 'bogus'])->assertJsonValidationErrors(['category']);
        $this->raise(0)->assertCreated();
    }

    public function test_evidence_photos_must_be_the_posters_own_uploads(): void
    {
        $this->raise(0, ['evidence_photos' => $this->proofPhotosFor($this->workers[0])])
            ->assertJsonValidationErrors(['evidence_photos.0']);

        $this->assertSame(0, TaskDispute::query()->count());
        $this->assertSame(ActivityStatus::Submitted, $this->activities[0]->refresh()->status);
    }

    public function test_only_the_poster_can_dispute(): void
    {
        foreach ([$this->workers[0], $this->stranger] as $user) {
            $this->asUser($user)
                ->postJson(route('v1.activities.disputes.store', $this->activities[0]->ulid), [
                    'category' => 'late', 'reason' => 'Datang tiga jam lewat jadwal.',
                ])
                ->assertForbidden();
        }
    }

    public function test_a_worker_cannot_be_disputed_twice(): void
    {
        $this->raise(0)->assertCreated();

        $this->raise(0)
            ->assertUnprocessable()
            ->assertJsonPath('code', 'dispute_not_allowed')
            ->assertJsonPath('context.reason', 'wrong_status');

        $this->assertSame(1, TaskDispute::query()->count());
    }

    public function test_the_worker_responds_once(): void
    {
        $this->raise(0)->assertCreated();
        $url = route('v1.activities.dispute.respond', $this->activities[0]->ulid);
        $photos = $this->proofPhotosFor($this->workers[0]);

        $this->asUser($this->workers[0])
            ->postJson($url, ['response' => 'Macet di jalan, sudah kabari lewat chat.', 'evidence_photos' => $photos])
            ->assertOk()
            ->assertJsonPath('data.worker_response', 'Macet di jalan, sudah kabari lewat chat.')
            ->assertJsonPath('data.worker_evidence_photos', $photos);

        $this->asUser($this->workers[0])
            ->postJson($url, ['response' => 'Tanggapan kedua yang menimpa.'])
            ->assertUnprocessable()
            ->assertJsonPath('context.reason', 'already_responded');

        // Pemberi kerja & mitra lain bukan pemilik pekerjaan ini.
        $this->asUser($this->poster)->postJson($url, ['response' => 'Bukan hak saya.'])->assertForbidden();
        $this->asUser($this->workers[1])->postJson($url, ['response' => 'Bukan hak saya.'])->assertForbidden();
    }

    public function test_responding_without_an_open_dispute_is_refused(): void
    {
        $this->asUser($this->workers[0])
            ->postJson(route('v1.activities.dispute.respond', $this->activities[0]->ulid), ['response' => 'Belum ada sengketa.'])
            ->assertUnprocessable()
            ->assertJsonPath('context.reason', 'not_open');
    }

    public function test_workers_see_only_their_own_dispute(): void
    {
        $this->raise(0)->assertCreated();
        $this->raise(1)->assertCreated();
        $url = route('v1.tasks.disputes.index', $this->task->ulid);

        $this->asUser($this->poster)->getJson($url)->assertOk()->assertJsonCount(2, 'data');
        $this->asUser($this->workers[0])->getJson($url)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.activity_id', $this->activities[0]->ulid);
        $this->asUser($this->stranger)->getJson($url)->assertForbidden();
    }

    public function test_the_admin_must_explain_the_decision(): void
    {
        $this->raise(0)->assertCreated();
        $dispute = TaskDispute::query()->sole();

        $this->asAdmin($this->activeAdmin())
            ->postJson(route('v1.admin.disputes.resolve', $dispute->ulid), ['resolution' => 'refund'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note']);

        $this->assertTrue($dispute->refresh()->status->isOpen());
    }

    public function test_the_admin_resolves_one_worker_with_an_audited_note(): void
    {
        $this->raise(0)->assertCreated();
        $admin = $this->activeAdmin();
        $dispute = TaskDispute::query()->sole();

        $this->asAdmin($admin)
            ->getJson(route('v1.admin.disputes.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asAdmin($admin)
            ->postJson(route('v1.admin.disputes.resolve', $dispute->ulid), [
                'resolution' => 'refund',
                'note' => 'Jam tiba tercatat 3 jam lewat jadwal.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolution', 'refund')
            ->assertJsonPath('data.admin_note', 'Jam tiba tercatat 3 jam lewat jadwal.');

        $this->assertSame(ActivityStatus::Refunded, $this->activities[0]->refresh()->status);
        // Mitra lain masih menunggu penilaian pemberi kerja.
        $this->assertSame(TaskStatus::Submitted, $this->task->refresh()->status);
        $this->assertTrue(AdminAuditLog::query()
            ->where('action', AdminAction::DisputeRefunded)
            ->where('subject_id', $dispute->getKey())
            ->where('reason', 'Jam tiba tercatat 3 jam lewat jadwal.')
            ->exists());

        $this->asAdmin($admin)
            ->postJson(route('v1.admin.disputes.resolve', $dispute->ulid), [
                'resolution' => 'release',
                'note' => 'Coba putuskan dua kali.',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'dispute_already_resolved');
    }

    /** Semua mitra dikembalikan → task `refunded`, seluruh tagihan kembali. */
    public function test_refunding_every_worker_refunds_the_task(): void
    {
        $this->raise(0)->assertCreated();
        $this->raise(1)->assertCreated();
        $admin = $this->activeAdmin();

        foreach (TaskDispute::query()->get() as $dispute) {
            $this->asAdmin($admin)
                ->postJson(route('v1.admin.disputes.resolve', $dispute->ulid), [
                    'resolution' => 'refund', 'note' => 'Pekerjaan tidak dikerjakan.',
                ])
                ->assertOk();
        }

        $this->assertSame(TaskStatus::Refunded, $this->task->refresh()->status);
        $this->assertSame(250_000, (int) $this->poster->refresh()->walletOrNew()->balance);
    }
}
