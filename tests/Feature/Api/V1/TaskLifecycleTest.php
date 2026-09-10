<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TaskStatus;
use App\Models\Admin;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Task, lelang, uang, activity, penilaian — lewat HTTP. */
final class TaskLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private User $stranger;

    private ?Admin $admin = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();
        $this->stranger = $this->activeUser();
    }

    /**
     * Pengelola yang mengonfirmasi transfer.
     *
     * Dibuat sekali per test: sejak `held` hanya bisa dicapai dari sisi
     * pengelola, hampir setiap alur uang di kelas ini membutuhkannya.
     */
    private function admin(): Admin
    {
        return $this->admin ??= $this->activeAdmin();
    }

    private function payload(array $override = []): array
    {
        return [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Cuci AC 2 unit di rumah',
            'description' => 'Servis AC split, freon dan cuci evaporator.',
            'budget_min' => 150_000,
            'city' => 'Jakarta',
            'publish_now' => true,
            ...$override,
        ];
    }

    private function createTask(array $override = []): string
    {
        return $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload($override))
            ->assertCreated()
            ->json('data.id');
    }

    // ── buat task ───────────────────────────────────────────────────────────

    public function test_creating_a_task_returns_the_full_shape(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['skills' => ['cuci-ac']]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.budget.min', 150_000)
            ->assertJsonPath('data.budget.max', null)
            ->assertJsonPath('data.skills.0.slug', 'cuci-ac')
            ->assertJsonStructure([
                'data' => [
                    'id', 'task_number', 'title', 'description', 'status', 'options', 'photos',
                    'budget' => ['min', 'max', 'reference_median'],
                    'location' => ['text', 'city', 'latitude', 'longitude', 'is_remote'],
                    'bids_count', 'agreed_amount', 'skills', 'category', 'poster',
                    'created_at', 'updated_at',
                ],
            ]);
    }

    public function test_without_publish_now_the_task_is_a_draft(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['publish_now' => false]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_creating_a_task_validates_required_fields(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'title', 'description', 'budget_min']);
    }

    public function test_budget_max_below_min_is_rejected(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['budget_max' => 100_000]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['budget_max']);
    }

    public function test_budget_max_equal_to_min_is_accepted(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['budget_max' => 150_000]))
            ->assertCreated()
            ->assertJsonPath('data.budget.max', 150_000);
    }

    public function test_unknown_category_is_rejected(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['category_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_unknown_skill_is_rejected(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['skills' => ['bukan-skill']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['skills.0']);
    }

    public function test_invalid_coordinates_are_rejected(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['latitude' => 200, 'longitude' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_a_past_needed_at_is_rejected(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['needed_at' => now()->subDay()->toIso8601String()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['needed_at']);
    }

    public function test_options_require_a_label(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['options' => [['value' => true]]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['options.0.label']);
    }

    // ── lihat, terbitkan, batalkan ──────────────────────────────────────────

    public function test_the_poster_can_see_their_own_task(): void
    {
        $id = $this->createTask();

        $this->asUser($this->poster)->getJson(route('v1.tasks.show', $id))->assertOk();
    }

    public function test_a_stranger_can_see_an_open_task(): void
    {
        $id = $this->createTask();

        $this->asUser($this->stranger)->getJson(route('v1.tasks.show', $id))->assertOk();
    }

    public function test_a_stranger_cannot_see_a_draft(): void
    {
        $id = $this->createTask(['publish_now' => false]);

        $this->asUser($this->stranger)->getJson(route('v1.tasks.show', $id))->assertForbidden();
    }

    public function test_only_the_poster_can_publish(): void
    {
        $id = $this->createTask(['publish_now' => false]);

        $this->asUser($this->stranger)->postJson(route('v1.tasks.publish', $id))->assertForbidden();
        $this->asUser($this->poster)->postJson(route('v1.tasks.publish', $id))
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_publishing_a_completed_task_is_rejected(): void
    {
        $id = $this->createTask(['publish_now' => false]);
        Task::query()->where('ulid', $id)->update(['status' => TaskStatus::Completed]);

        $this->asUser($this->poster)->postJson(route('v1.tasks.publish', $id))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_status_transition');
    }

    public function test_the_poster_can_cancel_with_a_reason(): void
    {
        $id = $this->createTask();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel', $id), ['reason' => 'rencana berubah'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancelled_by', 'poster');
    }

    public function test_a_stranger_cannot_cancel(): void
    {
        $id = $this->createTask();

        $this->asUser($this->stranger)->postJson(route('v1.tasks.cancel', $id))->assertForbidden();
    }

    public function test_cancel_validates_the_reason_length(): void
    {
        $id = $this->createTask();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel', $id), ['reason' => str_repeat('a', 300)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_an_unknown_task_is_not_found(): void
    {
        $this->asUser($this->poster)
            ->getJson(route('v1.tasks.show', str_repeat('Z', 26)))
            ->assertNotFound();
    }

    // ── daftar milik sendiri ────────────────────────────────────────────────

    public function test_posted_and_worked_lists(): void
    {
        $this->createTask();

        $this->asUser($this->poster)->getJson(route('v1.tasks.posted'))
            ->assertOk()->assertJsonCount(1, 'data');
        $this->asUser($this->poster)->getJson(route('v1.tasks.worked'))
            ->assertOk()->assertJsonCount(0, 'data');
        $this->asUser($this->worker)->getJson(route('v1.tasks.posted'))
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_posted_list_can_filter_by_status(): void
    {
        $this->createTask();
        $this->createTask(['publish_now' => false]);

        $this->asUser($this->poster)
            ->getJson(route('v1.tasks.posted', ['status' => 'draft']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'draft');
    }

    public function test_list_rejects_an_unknown_status(): void
    {
        $this->asUser($this->poster)
            ->getJson(route('v1.tasks.posted', ['status' => 'entah']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ── lelang ──────────────────────────────────────────────────────────────

    public function test_placing_a_bid_returns_the_full_shape(): void
    {
        $id = $this->createTask();

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $id), [
                'amount' => 220_000,
                'message' => 'Bawa alat sendiri',
                'estimated_hours' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.amount', 220_000)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonStructure([
                'data' => ['id', 'amount', 'message', 'option_responses', 'estimated_hours',
                    'can_start_at', 'status', 'responded_at', 'bidder', 'created_at'],
            ]);
    }

    public function test_re_bidding_returns_200_not_201(): void
    {
        $id = $this->createTask();
        $this->asUser($this->worker)->postJson(route('v1.tasks.bids.store', $id), ['amount' => 220_000])
            ->assertCreated();

        $this->asUser($this->worker)->postJson(route('v1.tasks.bids.store', $id), ['amount' => 210_000])
            ->assertOk()
            ->assertJsonPath('data.amount', 210_000);
    }

    public function test_bidding_on_own_task_is_forbidden(): void
    {
        $id = $this->createTask();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.bids.store', $id), ['amount' => 200_000])
            ->assertForbidden()
            ->assertJsonPath('code', 'cannot_bid_own_task');
    }

    public function test_a_bid_below_the_minimum_is_rejected(): void
    {
        $id = $this->createTask();

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $id), ['amount' => 100_000])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'bid_below_minimum')
            ->assertJsonPath('context.budget_min', 150_000);
    }

    public function test_a_bid_above_budget_max_is_allowed(): void
    {
        $id = $this->createTask(['budget_max' => 200_000]);

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $id), ['amount' => 500_000])
            ->assertCreated();
    }

    public function test_bidding_validates_the_amount(): void
    {
        $id = $this->createTask();

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $id), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_only_the_poster_can_list_bids(): void
    {
        $id = $this->createTask();
        $this->asUser($this->worker)->postJson(route('v1.tasks.bids.store', $id), ['amount' => 220_000]);

        $this->asUser($this->worker)->getJson(route('v1.tasks.bids.index', $id))->assertForbidden();
        $this->asUser($this->poster)->getJson(route('v1.tasks.bids.index', $id))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data' => [['bidder' => ['identity_verified', 'as_worker', 'as_poster']]]]);
    }

    public function test_bids_can_be_sorted(): void
    {
        $id = $this->createTask();
        $this->asUser($this->worker)->postJson(route('v1.tasks.bids.store', $id), ['amount' => 220_000]);
        $this->asUser($this->stranger)->postJson(route('v1.tasks.bids.store', $id), ['amount' => 180_000]);

        $amounts = $this->asUser($this->poster)
            ->getJson(route('v1.tasks.bids.index', [$id, 'sort' => 'amount']))
            ->assertOk()
            ->json('data.*.amount');

        $this->assertSame([180_000, 220_000], $amounts);
    }

    public function test_bids_reject_an_unknown_sort(): void
    {
        $id = $this->createTask();

        $this->asUser($this->poster)
            ->getJson(route('v1.tasks.bids.index', [$id, 'sort' => 'harga']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sort']);
    }

    public function test_my_bids_lists_only_mine(): void
    {
        $id = $this->createTask();
        $this->asUser($this->worker)->postJson(route('v1.tasks.bids.store', $id), ['amount' => 220_000]);

        $this->asUser($this->worker)->getJson(route('v1.bids.mine'))->assertOk()->assertJsonCount(1, 'data');
        $this->asUser($this->stranger)->getJson(route('v1.bids.mine'))->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_only_the_bidder_can_withdraw(): void
    {
        $id = $this->createTask();
        $bid = $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $id), ['amount' => 220_000])
            ->json('data.id');

        $this->asUser($this->stranger)->postJson(route('v1.bids.withdraw', $bid))->assertForbidden();
        $this->asUser($this->worker)->postJson(route('v1.bids.withdraw', $bid))
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');
    }

    // ── deal → uang → activity → selesai ────────────────────────────────────

    /** @return array{0:string,1:string,2:string} task, bid, activity */
    private function throughToActivity(): array
    {
        $task = $this->createTask();
        $bid = $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => 220_000])
            ->json('data.id');

        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bid))
            ->assertOk()
            ->assertJsonPath('data.status', 'dealt')
            ->assertJsonPath('data.agreed_amount', 220_000);

        // Pemberi kerja MELAPOR sudah transfer. Ini tidak membuka apa pun —
        // dan itu inti perubahannya: dulu langkah ini langsung memindahkan
        // tagihan ke `held`, yang berarti pemberi kerja menyatakan sendiri
        // uangnya sudah masuk.
        $payment = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.payment.hold', $task))
            ->assertOk()
            ->assertJsonPath('data.status', 'awaiting_confirmation')
            ->assertJsonPath('data.awaits_confirmation', true)
            ->assertJsonPath('data.is_held', false)
            ->json('data.id');

        // Yang menahan dana — dan dengan itu membuka activity — pengelola.
        $this->asAdmin($this->admin())
            ->postJson(route('v1.admin.payments.confirm', $payment))
            ->assertOk()
            ->assertJsonPath('data.is_held', true);

        $activity = $this->asUser($this->worker)
            ->getJson(route('v1.activities.mine'))
            ->assertOk()
            ->json('data.0.id');

        return [$task, $bid, $activity];
    }

    public function test_only_the_poster_can_accept_a_bid(): void
    {
        $task = $this->createTask();
        $bid = $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => 220_000])
            ->json('data.id');

        $this->asUser($this->stranger)->postJson(route('v1.bids.accept', $bid))->assertForbidden();
    }

    public function test_accepting_a_second_bid_conflicts(): void
    {
        $task = $this->createTask();
        $a = $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => 220_000])->json('data.id');
        $b = $this->asUser($this->stranger)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => 180_000])->json('data.id');

        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $a))->assertOk();
        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $b))
            ->assertStatus(409)
            ->assertJsonPath('code', 'task_already_dealt');
    }

    public function test_payment_status_is_visible_after_the_deal(): void
    {
        $task = $this->createTask();
        $bid = $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => 220_000])->json('data.id');
        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bid))->assertOk();

        $this->asUser($this->poster)->getJson(route('v1.tasks.payment.show', $task))
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_held', false)
            ->assertJsonStructure(['data' => ['id', 'status', 'amount', 'is_held',
                'awaits_confirmation', 'reported_at', 'rejection_reason',
                'paid_at', 'held_at', 'released_at', 'refunded_at', 'cancelled_at', 'created_at']]);
    }

    public function test_only_the_poster_can_pay(): void
    {
        $task = $this->createTask();
        $bid = $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => 220_000])->json('data.id');
        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bid))->assertOk();

        $this->asUser($this->worker)->postJson(route('v1.tasks.payment.hold', $task))->assertForbidden();
    }

    /**
     * Pemberi kerja TIDAK bisa memanggil endpoint pengelola.
     *
     * 401, bukan 403: guard `admin` hanya menerima pemilik token dari tabel
     * `admins`, jadi token pengguna gagal di autentikasi — bukan di otorisasi.
     */
    public function test_the_poster_cannot_confirm_their_own_transfer(): void
    {
        $task = $this->createTask();
        $bid = $this->asUser($this->worker)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => 220_000])->json('data.id');
        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bid))->assertOk();

        $payment = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.payment.hold', $task))
            ->assertOk()
            ->json('data.id');

        $this->asUser($this->poster)
            ->postJson(route('v1.admin.payments.confirm', $payment))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        // Dan tidak ada apa pun yang terbuka.
        $this->asUser($this->poster)->getJson(route('v1.tasks.payment.show', $task))
            ->assertOk()
            ->assertJsonPath('data.is_held', false);
    }

    public function test_confirming_opens_the_activity(): void
    {
        [$task, , $activity] = $this->throughToActivity();

        $this->asUser($this->worker)->getJson(route('v1.activities.show', $activity))
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.payment.is_held', true)
            ->assertJsonPath('data.task.status', 'active')
            ->assertJsonStructure(['data' => ['id', 'status', 'agreed_amount', 'opened_at', 'started_at',
                'submitted_at', 'approved_at', 'rejected_at', 'worker_note', 'proof_photos',
                'poster_note', 'task', 'worker', 'payment', 'created_at']]);
        $this->assertNotEmpty($task);
    }

    public function test_reporting_a_transfer_again_after_it_was_held_is_rejected(): void
    {
        [$task] = $this->throughToActivity();

        $this->asUser($this->poster)->postJson(route('v1.tasks.payment.hold', $task))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_status_transition');
    }

    public function test_only_the_worker_can_start_and_submit(): void
    {
        [, , $activity] = $this->throughToActivity();

        $this->asUser($this->poster)->postJson(route('v1.activities.start', $activity))->assertForbidden();
        $this->asUser($this->worker)->postJson(route('v1.activities.start', $activity))
            ->assertOk()->assertJsonPath('data.status', 'in_progress');

        $this->asUser($this->poster)->postJson(route('v1.activities.submit', $activity))->assertForbidden();
        $this->asUser($this->worker)->postJson(route('v1.activities.submit', $activity), [
            'worker_note' => 'Sudah beres',
            'proof_photos' => ['p/a.jpg', 'p/b.jpg'],
        ])->assertOk()->assertJsonCount(2, 'data.proof_photos');
    }

    public function test_submit_validates_the_photo_list(): void
    {
        [, , $activity] = $this->throughToActivity();
        $this->asUser($this->worker)->postJson(route('v1.activities.start', $activity));

        $this->asUser($this->worker)->postJson(route('v1.activities.submit', $activity), [
            'proof_photos' => array_fill(0, 11, 'p/a.jpg'),
        ])->assertUnprocessable()->assertJsonValidationErrors(['proof_photos']);
    }

    private function submitted(): array
    {
        [$task, $bid, $activity] = $this->throughToActivity();
        $this->asUser($this->worker)->postJson(route('v1.activities.start', $activity))->assertOk();
        $this->asUser($this->worker)->postJson(route('v1.activities.submit', $activity), [
            'worker_note' => 'Beres', 'proof_photos' => ['p/a.jpg'],
        ])->assertOk();

        return [$task, $bid, $activity];
    }

    public function test_only_the_poster_can_judge(): void
    {
        [, , $activity] = $this->submitted();

        $this->asUser($this->worker)->postJson(route('v1.activities.approve', $activity))->assertForbidden();
        $this->asUser($this->worker)->postJson(route('v1.activities.reject', $activity))->assertForbidden();
    }

    public function test_approving_completes_the_task_and_releases_the_money(): void
    {
        [$task, , $activity] = $this->submitted();

        $this->asUser($this->poster)
            ->postJson(route('v1.activities.approve', $activity), ['poster_note' => 'Rapi'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.task.status', 'completed')
            ->assertJsonPath('data.payment.status', 'released');

        $this->asUser($this->poster)->getJson(route('v1.tasks.payment.show', $task))
            ->assertJsonPath('data.status', 'released');
    }

    public function test_rejecting_disputes_the_task_and_keeps_the_money(): void
    {
        [$task, , $activity] = $this->submitted();

        $this->asUser($this->poster)
            ->postJson(route('v1.activities.reject', $activity), ['poster_note' => 'Belum bersih'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.task.status', 'disputed')
            ->assertJsonPath('data.payment.status', 'held');

        $this->assertNotEmpty($task);
    }

    public function test_my_activities_lists_only_mine(): void
    {
        $this->throughToActivity();

        $this->asUser($this->worker)->getJson(route('v1.activities.mine'))
            ->assertOk()->assertJsonCount(1, 'data');
        $this->asUser($this->stranger)->getJson(route('v1.activities.mine'))
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_stranger_cannot_view_an_activity(): void
    {
        [, , $activity] = $this->throughToActivity();

        $this->asUser($this->stranger)->getJson(route('v1.activities.show', $activity))->assertForbidden();
    }

    // ── penilaian ───────────────────────────────────────────────────────────

    public function test_reviews_flow_both_ways(): void
    {
        [$task, , $activity] = $this->submitted();
        $this->asUser($this->poster)->postJson(route('v1.activities.approve', $activity))->assertOk();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.reviews.store', $task), ['rating' => 5, 'comment' => 'Bagus'])
            ->assertCreated()
            ->assertJsonPath('data.reviewer_role', 'poster')
            ->assertJsonStructure(['data' => ['id', 'rating', 'comment', 'reviewer_role', 'reviewer', 'created_at']]);

        $this->asUser($this->worker)
            ->postJson(route('v1.tasks.reviews.store', $task), ['rating' => 4])
            ->assertCreated()
            ->assertJsonPath('data.reviewer_role', 'worker');
    }

    public function test_reviewing_before_completion_is_rejected(): void
    {
        [$task] = $this->throughToActivity();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.reviews.store', $task), ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'review_not_allowed');
    }

    public function test_reviewing_twice_is_rejected(): void
    {
        [$task, , $activity] = $this->submitted();
        $this->asUser($this->poster)->postJson(route('v1.activities.approve', $activity))->assertOk();
        $this->asUser($this->poster)->postJson(route('v1.tasks.reviews.store', $task), ['rating' => 5])->assertCreated();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.reviews.store', $task), ['rating' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'review_not_allowed');
    }

    public function test_a_stranger_cannot_review(): void
    {
        [$task, , $activity] = $this->submitted();
        $this->asUser($this->poster)->postJson(route('v1.activities.approve', $activity))->assertOk();

        $this->asUser($this->stranger)
            ->postJson(route('v1.tasks.reviews.store', $task), ['rating' => 5])
            ->assertForbidden();
    }

    public function test_review_validates_the_rating_range(): void
    {
        [$task, , $activity] = $this->submitted();
        $this->asUser($this->poster)->postJson(route('v1.activities.approve', $activity))->assertOk();

        foreach ([0, 6, 'x'] as $rating) {
            $this->asUser($this->poster)
                ->postJson(route('v1.tasks.reviews.store', $task), ['rating' => $rating])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['rating']);
        }
    }

    public function test_received_reviews_can_be_filtered_by_role(): void
    {
        [$task, , $activity] = $this->submitted();
        $this->asUser($this->poster)->postJson(route('v1.activities.approve', $activity))->assertOk();
        $this->asUser($this->poster)->postJson(route('v1.tasks.reviews.store', $task), ['rating' => 5])->assertCreated();

        $this->asUser($this->worker)
            ->getJson(route('v1.users.reviews.index', [$this->worker->ulid, 'role' => 'poster']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->asUser($this->worker)
            ->getJson(route('v1.users.reviews.index', [$this->worker->ulid, 'role' => 'worker']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_received_reviews_reject_an_unknown_role(): void
    {
        $this->asUser($this->worker)
            ->getJson(route('v1.users.reviews.index', [$this->worker->ulid, 'role' => 'admin']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }
}
