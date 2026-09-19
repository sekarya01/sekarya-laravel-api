<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur lengkap task banyak pekerja, lewat HTTP.
 *
 * Test unit membuktikan aturannya. Kelas ini menjawab pertanyaan yang berbeda:
 * apakah seluruh jalurnya benar tersambung — rute, otorisasi, validasi, dan
 * bentuk respons — untuk sebuah pekerjaan yang merekrut tiga orang sekaligus.
 */
final class MultiWorkerTaskTest extends TestCase
{
    protected bool $fundUsers = true;

    use RefreshDatabase;

    private User $poster;

    private ?Admin $admin = null;

    /** @var list<User> */
    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->workers = [$this->activeUser(), $this->activeUser(), $this->activeUser()];
    }

    /** @return array<string, mixed> */
    private function payload(array $override = []): array
    {
        return [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Bersih-bersih gudang',
            'description' => 'Butuh beberapa orang untuk merapikan gudang dalam sehari.',
            'budget_min' => 150_000,
            'city' => 'Jakarta',
            'needed_at' => now()->addDays(3)->toIso8601String(),
            'publish_now' => true,
            ...$override,
        ];
    }

    private function createTask(int $workersNeeded): string
    {
        return $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['workers_needed' => $workersNeeded]))
            ->assertCreated()
            ->json('data.id');
    }

    private function apply(User $worker, string $task, int $amount): string
    {
        return $this->asUser($worker)
            ->postJson(route('v1.tasks.bids.store', $task), ['amount' => $amount])
            ->assertCreated()
            ->json('data.id');
    }

    // ── Membuat & kuota ─────────────────────────────────────────────────────

    public function test_a_poster_sets_how_many_workers_are_needed(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload(['workers_needed' => 30]))
            ->assertCreated()
            ->assertJsonPath('data.hiring.workers_needed', 30)
            ->assertJsonPath('data.hiring.workers_hired', 0)
            ->assertJsonPath('data.hiring.slots_remaining', 30);
    }

    public function test_a_task_defaults_to_one_worker(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.hiring.workers_needed', 1);
    }

    public function test_the_worker_count_is_validated(): void
    {
        foreach ([0, -3, 501] as $invalid) {
            $this->asUser($this->poster)
                ->postJson(route('v1.tasks.store'), $this->payload(['workers_needed' => $invalid]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['workers_needed']);
        }
    }

    /**
     * `workers_needed` membatasi berapa yang DITERIMA, bukan berapa yang boleh
     * melamar — pemberi kerja tetap memilih dari seluruh penawaran yang masuk.
     */
    public function test_the_auction_stays_open_to_more_people_than_there_are_slots(): void
    {
        $task = $this->createTask(2);

        foreach ($this->workers as $i => $worker) {
            $this->apply($worker, $task, 200_000 - $i * 10_000);
        }

        $this->asUser($this->poster)
            ->getJson(route('v1.tasks.bids.index', [$task, 'sort' => 'amount']))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            // Diurutkan menaik: bahan pertimbangan pemberi kerja.
            ->assertJsonPath('data.0.amount', 180_000);

        $this->asUser($this->poster)
            ->getJson(route('v1.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.bids_count', 3)
            ->assertJsonPath('data.hiring.workers_needed', 2);
    }

    /** Mengisi slot terakhir menutup lelang; yang menunggu ikut ditutup. */
    public function test_filling_the_last_slot_closes_the_auction(): void
    {
        $task = $this->createTask(2);
        $bids = [];
        foreach ($this->workers as $i => $worker) {
            $bids[] = $this->apply($worker, $task, 200_000 - $i * 10_000);
        }

        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bids[0]))->assertOk();
        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bids[1]))
            ->assertOk()
            // Slot terakhir menutup lelang DAN membuka pekerjaannya.
            ->assertJsonPath('data.status', 'active');

        $this->assertSame('rejected', $this->asUser($this->workers[2])
            ->getJson(route('v1.bids.mine'))->json('data.0.status'));

        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bids[2]))
            ->assertStatus(409)
            ->assertJsonPath('code', 'task_already_dealt');
    }

    // ── Merekrut ────────────────────────────────────────────────────────────

    public function test_hiring_fills_the_slots_one_by_one(): void
    {
        $task = $this->createTask(3);
        $bids = [];
        foreach ($this->workers as $i => $worker) {
            $bids[] = $this->apply($worker, $task, 200_000 + $i * 10_000);
        }

        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bids[0]))
            ->assertOk()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.hiring.workers_hired', 1)
            ->assertJsonPath('data.hiring.slots_remaining', 2);

        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bids[1]))
            ->assertOk()
            ->assertJsonPath('data.status', 'open');

        // Slot terakhir menutup lelang.
        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bids[2]))
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.hiring.slots_remaining', 0)
            ->assertJsonPath('data.agreed_amount', 630_000);
    }

    /** Semua pekerja terlihat pada task, bukan hanya satu. */
    public function test_the_task_lists_every_hired_worker(): void
    {
        $task = $this->dealtTask(2);

        $names = $this->asUser($this->poster)
            ->getJson(route('v1.tasks.show', $task))
            ->assertOk()
            ->json('data.workers.*.id');

        $this->assertCount(2, $names);
    }

    // ── Mulai lebih awal ────────────────────────────────────────────────────

    public function test_the_poster_can_start_with_fewer_workers_than_planned(): void
    {
        $task = $this->createTask(3);
        $accepted = $this->apply($this->workers[0], $task, 200_000);
        $waiting = $this->apply($this->workers[1], $task, 210_000);

        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $accepted))->assertOk();

        $this->asUser($this->poster)->postJson(route('v1.tasks.start', $task))
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            // Target diturunkan ke kenyataan, bukan dibiarkan kekurangan dua
            // orang selamanya.
            ->assertJsonPath('data.hiring.workers_needed', 1)
            ->assertJsonPath('data.hiring.slots_remaining', 0);

        // Pelamar yang menunggu ditutup, bukan dibiarkan menggantung.
        $this->assertSame('rejected', $this->asUser($this->workers[1])
            ->getJson(route('v1.bids.mine'))->json('data.0.status'));
        $this->assertNotNull($waiting);
    }

    public function test_starting_without_anyone_hired_is_refused(): void
    {
        $task = $this->createTask(3);
        $this->apply($this->workers[0], $task, 200_000);

        $this->asUser($this->poster)->postJson(route('v1.tasks.start', $task))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'no_workers_hired');
    }

    public function test_only_the_poster_can_start_the_task(): void
    {
        $task = $this->createTask(3);
        $bid = $this->apply($this->workers[0], $task, 200_000);
        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bid))->assertOk();

        $this->asUser($this->workers[0])
            ->postJson(route('v1.tasks.start', $task))
            ->assertForbidden();
    }

    // ── Bekerja & dibayar ───────────────────────────────────────────────────

    /** @return string ULID task yang sudah deal dengan $count pekerja */
    private function dealtTask(int $count): string
    {
        $task = $this->createTask($count);

        foreach (array_slice($this->workers, 0, $count) as $i => $worker) {
            $bid = $this->apply($worker, $task, 200_000 + $i * 10_000);
            $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bid))->assertOk();
        }

        return $task;
    }

    /**
     * Pengelola yang mengonfirmasi transfer. Satu per test.
     *
     * `held` hanya bisa dicapai dari sisi pengelola, jadi setiap alur yang
     * sampai ke activity lewat sini.
     */
    private function admin(): Admin
    {
        return $this->admin ??= $this->activeAdmin();
    }

    /**
     * Activity tiap pekerja. Dana sudah ditahan dari saldo sejak tugas
     * dipasang, jadi deal langsung membuka pekerjaan yang dibiayai — tidak ada
     * transfer yang perlu dilaporkan atau dikonfirmasi.
     *
     * @return list<string> id activity, URUT sesuai urutan $this->workers
     */
    private function confirmTransfer(string $task, int $workerCount): array
    {
        $this->asUser($this->poster)
            ->getJson(route('v1.tasks.payment.show', $task))
            ->assertOk()
            ->assertJsonPath('data.is_held', true);

        $ids = [];

        foreach (array_slice($this->workers, 0, $workerCount) as $worker) {
            $ids[] = $this->asUser($worker)
                ->getJson(route('v1.activities.mine'))
                ->assertOk()
                ->json('data.0.id');
        }

        return $ids;
    }

    /** Dana ditahan sejak tugas dipasang → setiap pekerja langsung boleh berangkat. */
    public function test_a_funded_deal_lets_every_worker_depart(): void
    {
        $task = $this->dealtTask(3);

        foreach (array_slice($this->workers, 0, 3) as $worker) {
            $activity = $this->asUser($worker)->getJson(route('v1.activities.mine'))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.status', 'open')
                ->json('data.0.id');

            $this->asUser($worker)->postJson(route('v1.activities.depart', $activity))
                ->assertOk();
        }

        $this->asUser($this->poster)->getJson(route('v1.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    /**
     * Contoh aturan pemotongan: 3 pekerja × Rp150.000 ditahan saat dipasang;
     * mulai dengan 1 orang (penawaran Rp200.000) → yang terpotong Rp200.000,
     * sisanya kembali ke saldo.
     */
    public function test_only_hired_workers_are_charged_when_starting_with_fewer(): void
    {
        $before = (int) $this->poster->walletOrNew()->balance;
        $task = $this->createTask(3);

        $this->assertSame($before - 450_000, (int) $this->poster->fresh()->walletOrNew()->balance);

        $bid = $this->apply($this->workers[0], $task, 200_000);
        $this->asUser($this->poster)->postJson(route('v1.bids.accept', $bid))->assertOk();
        $this->asUser($this->poster)->postJson(route('v1.tasks.start', $task))->assertOk();

        $this->assertSame($before - 200_000, (int) $this->poster->fresh()->walletOrNew()->balance);
        $this->asUser($this->poster)->getJson(route('v1.tasks.payment.show', $task))
            ->assertJsonPath('data.amount', 200_000)
            ->assertJsonPath('data.is_held', true);
    }

    /** Saldo kurang → tugas tidak terpasang sama sekali. */
    public function test_posting_without_enough_balance_is_refused(): void
    {
        $broke = User::factory()->create();
        $broke->status = \App\Enums\UserStatus::Active;
        $broke->email_verified_at = now();
        $broke->save();

        $this->asUser($broke)
            ->postJson(route('v1.tasks.store'), $this->payload(['workers_needed' => 2]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'insufficient_balance');

        $this->assertSame(0, \App\Models\Task::query()->where('poster_id', $broke->getKey())->count());
    }

    public function test_one_transfer_opens_an_activity_for_every_worker(): void
    {
        $task = $this->dealtTask(3);

        $this->confirmTransfer($task, 3);

        $amounts = [];
        $statuses = [];

        foreach (array_slice($this->workers, 0, 3) as $worker) {
            $activity = $this->asUser($worker)
                ->getJson(route('v1.activities.mine'))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->json('data.0');

            $amounts[] = $activity['agreed_amount'];
            $statuses[] = $activity['status'];
        }

        // Harga PER ORANG, dari penawarannya sendiri — bukan total task.
        $this->assertSame([200_000, 210_000, 220_000], $amounts);
        $this->assertSame(['open', 'open', 'open'], $statuses);

        $this->asUser($this->poster)->getJson(route('v1.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    /** Antar satu pekerja sampai lokasi: berangkat, lalu diakui pemberi kerja. */
    private function bringToSiteViaApi(string $activity, User $worker): void
    {
        $this->asUser($worker)->postJson(route('v1.activities.depart', $activity))->assertOk();
        $this->asUser($this->poster)->postJson(route('v1.activities.arrived', $activity))->assertOk();
    }

    public function test_each_worker_only_sees_and_drives_their_own_activity(): void
    {
        $task = $this->dealtTask(2);
        $ids = $this->confirmTransfer($task, 2);

        $this->bringToSiteViaApi($ids[0], $this->workers[0]);

        // Pekerja kedua tidak boleh menyentuh pekerjaan pekerja pertama.
        $this->asUser($this->workers[1])
            ->postJson(route('v1.activities.start', $ids[0]))
            ->assertForbidden();

        $this->asUser($this->workers[0])
            ->postJson(route('v1.activities.start', $ids[0]))
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->asUser($this->workers[0])->getJson(route('v1.activities.mine'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_task_finishes_only_after_every_worker_is_approved(): void
    {
        $task = $this->dealtTask(2);
        $ids = $this->confirmTransfer($task, 2);

        foreach ([0, 1] as $i) {
            $this->bringToSiteViaApi($ids[$i], $this->workers[$i]);
            $this->asUser($this->workers[$i])->postJson(route('v1.activities.start', $ids[$i]))->assertOk();
            $this->asUser($this->workers[$i])
                ->postJson(route('v1.activities.submit', $ids[$i]), ['worker_note' => 'beres'])
                ->assertOk();
        }

        $this->asUser($this->poster)->postJson(route('v1.activities.approve', $ids[0]))->assertOk();

        // Satu disetujui: task belum selesai, dana belum dilepas.
        $this->asUser($this->poster)->getJson(route('v1.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.payment.is_held', true);

        $this->asUser($this->poster)->postJson(route('v1.activities.approve', $ids[1]))->assertOk();

        $this->asUser($this->poster)->getJson(route('v1.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Upah masing-masing — harga penawarannya sendiri — masuk ke saldonya.
        foreach ([0 => 200_000, 1 => 210_000] as $i => $pay) {
            $this->assertSame(
                self::FUNDED_BALANCE + $pay,
                (int) $this->workers[$i]->fresh()->walletOrNew()->balance,
            );
        }
    }

    // ── Penilaian ───────────────────────────────────────────────────────────

    public function test_the_poster_reviews_each_worker_separately(): void
    {
        $task = $this->dealtTask(2);
        $ids = $this->confirmTransfer($task, 2);

        foreach ([0, 1] as $i) {
            $this->bringToSiteViaApi($ids[$i], $this->workers[$i]);
            $this->asUser($this->workers[$i])->postJson(route('v1.activities.start', $ids[$i]))->assertOk();
            $this->asUser($this->workers[$i])
                ->postJson(route('v1.activities.submit', $ids[$i]), ['worker_note' => 'beres'])->assertOk();
        }
        foreach ($ids as $id) {
            $this->asUser($this->poster)->postJson(route('v1.activities.approve', $id))->assertOk();
        }

        // Tanpa menyebut pekerja: ditolak, karena menebak sasarannya berarti
        // menaruh rating pada orang yang salah.
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.reviews.store', $task), ['rating' => 5])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'review_target_required');

        foreach ([0, 1] as $i) {
            $this->asUser($this->poster)
                ->postJson(route('v1.tasks.reviews.store', $task), [
                    'rating' => 4 + $i,
                    'worker_id' => $this->workers[$i]->ulid,
                ])
                ->assertCreated();
        }

        // Dan setiap pekerja menilai pemberi kerja.
        foreach ([0, 1] as $i) {
            $this->asUser($this->workers[$i])
                ->postJson(route('v1.tasks.reviews.store', $task), ['rating' => 5])
                ->assertCreated();
        }

        $this->asUser($this->poster)
            ->getJson(route('v1.users.reviews.index', $this->workers[0]->ulid))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
