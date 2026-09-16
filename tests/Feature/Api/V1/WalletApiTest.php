<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\WalletEntryType;
use App\Enums\WalletTopupStatus;
use App\Enums\WalletWithdrawalStatus;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTopup;
use App\Models\WalletWithdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saldo dari sisi penggunanya.
 *
 * Tiga arah uang masuk dan satu pintu keluar, semuanya lewat HTTP:
 * isi ulang (menunggu pengelola), pengembalian dana task yang batal, upah
 * pekerja saat dana dilepas — dan penarikan ke rekening terverifikasi.
 */
final class WalletApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->user = $this->activeUser();
    }

    // ── Membaca saldo ───────────────────────────────────────────────────────

    /**
     * Orang yang belum pernah menyentuh saldo mendapat bentuk yang SAMA
     * dengan yang sudah — bersaldo nol. Klien tidak perlu dua cabang.
     */
    public function test_a_fresh_account_has_a_zero_balance_and_no_wallet_row(): void
    {
        $this->asUser($this->user)->getJson(route('v1.me.wallet.show'))
            ->assertOk()
            ->assertJsonPath('data.balance', 0)
            ->assertJsonPath('data.id', null);

        $this->assertDatabaseMissing('wallets', ['user_id' => $this->user->getKey()]);
    }

    /** Dan membacanya tidak membuat baris — GET tidak boleh punya efek samping. */
    public function test_reading_the_balance_never_creates_a_wallet(): void
    {
        $this->asUser($this->user)->getJson(route('v1.me.wallet.show'))->assertOk();
        $this->asUser($this->user)->getJson(route('v1.me.wallet.show'))->assertOk();

        $this->assertSame(0, Wallet::query()
            ->where('user_id', $this->user->getKey())->count());
    }

    /** Saldo ikut di profil sendiri, supaya satu layar cukup satu permintaan. */
    public function test_the_profile_carries_the_balance(): void
    {
        $this->fundWallet($this->user, 125_000);

        $this->asUser($this->user)->getJson(route('v1.me.show'))
            ->assertOk()
            ->assertJsonPath('data.wallet.balance', 125_000);
    }

    public function test_the_history_reads_like_a_statement(): void
    {
        $this->fundWallet($this->user, 100_000);

        $response = $this->asUser($this->user)
            ->getJson(route('v1.me.wallet.entries.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $entry = $response->json('data.0');

        $this->assertSame('credit', $entry['direction']);
        $this->assertSame(100_000, $entry['amount']);
        $this->assertSame(100_000, $entry['balance_after']);
    }

    /**
     * Id internal kejadian tidak ikut keluar. Id berurutan membocorkan volume
     * bisnis — alasan yang sama membuat seluruh rute memakai ULID.
     */
    public function test_the_history_never_exposes_internal_reference_ids(): void
    {
        $this->fundWallet($this->user, 50_000);

        $entry = $this->asUser($this->user)
            ->getJson(route('v1.me.wallet.entries.index'))
            ->assertOk()
            ->json('data.0');

        $this->assertArrayNotHasKey('reference_id', $entry);
    }

    public function test_the_history_can_be_filtered_by_type_and_direction(): void
    {
        $this->fundWallet($this->user, 200_000);
        $this->verifyBankAccount($this->user);

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 50_000])
            ->assertCreated();

        $this->asUser($this->user)
            ->getJson(route('v1.me.wallet.entries.index', ['direction' => 'debit']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'withdrawal');

        $this->asUser($this->user)
            ->getJson(route('v1.me.wallet.entries.index', ['type' => 'withdrawal']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    /** Riwayat orang lain tidak pernah ikut. */
    public function test_the_history_only_shows_your_own_entries(): void
    {
        $other = $this->activeUser();
        $this->fundWallet($other, 900_000);
        $this->fundWallet($this->user, 10_000);

        $this->asUser($this->user)
            ->getJson(route('v1.me.wallet.entries.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.amount', 10_000);
    }

    // ── Isi saldo ───────────────────────────────────────────────────────────

    /**
     * INI YANG PALING PENTING DI SELURUH BERKAS: melapor sudah transfer TIDAK
     * menambah saldo. Kalau ia menambah, siapa pun bisa mengisi dompetnya
     * sendiri dengan satu permintaan HTTP — dan menariknya ke rekening.
     */
    public function test_requesting_a_topup_does_not_add_any_balance(): void
    {
        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.store'), [
                'amount' => 250_000,
                'sender_note' => 'BCA a.n. Budi',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', WalletTopupStatus::AwaitingConfirmation->value)
            ->assertJsonPath('data.awaits_confirmation', true)
            ->assertJsonPath('data.amount', 250_000);

        $this->assertSame(0, $this->user->fresh()->walletBalance());
    }

    public function test_a_topup_below_the_minimum_is_a_validation_error(): void
    {
        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.store'), ['amount' => 1_000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    /** Uang bilangan bulat. `numeric` akan menerima pecahan rupiah. */
    public function test_a_fractional_amount_is_refused(): void
    {
        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.store'), ['amount' => 10_000.5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    /** Statusnya tidak bisa dikirim klien — itu jalan mengisi saldo tanpa transfer. */
    public function test_the_status_cannot_be_dictated_by_the_payload(): void
    {
        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.store'), [
                'amount' => 100_000,
                'status' => WalletTopupStatus::Confirmed->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', WalletTopupStatus::AwaitingConfirmation->value);

        $this->assertSame(0, $this->user->fresh()->walletBalance());
    }

    /**
     * Antrean pengelola tidak boleh bisa dibanjiri satu akun. Rate limit
     * membatasi kecepatan; yang dijaga di sini JUMLAH yang menggantung.
     */
    public function test_too_many_pending_topups_are_refused(): void
    {
        $max = (int) config('sekarya.wallet.max_pending_requests');

        for ($i = 0; $i < $max; $i++) {
            $this->asUser($this->user)
                ->postJson(route('v1.me.wallet.topups.store'), ['amount' => 50_000])
                ->assertCreated();
        }

        $response = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.store'), ['amount' => 50_000])
            ->assertStatus(422);

        $this->assertSame('too_many_pending_wallet_requests', $this->errorCode($response));
        $this->assertSame($max, $response->json('context.max'));
    }

    /** Dan yang final tidak ikut dihitung. */
    public function test_finished_topups_do_not_count_against_the_quota(): void
    {
        WalletTopup::factory()->count(5)->confirmed()
            ->create(['user_id' => $this->user->getKey()]);

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.store'), ['amount' => 50_000])
            ->assertCreated();
    }

    public function test_a_pending_topup_can_be_cancelled_by_its_owner(): void
    {
        $topup = WalletTopup::factory()->create(['user_id' => $this->user->getKey()]);

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.cancel', $topup))
            ->assertOk()
            ->assertJsonPath('data.status', WalletTopupStatus::Cancelled->value);

        $this->assertSame(0, $this->user->fresh()->walletBalance());
    }

    public function test_someone_elses_topup_cannot_be_cancelled(): void
    {
        $topup = WalletTopup::factory()->create(['user_id' => $this->activeUser()->getKey()]);

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.cancel', $topup))
            ->assertForbidden();
    }

    public function test_a_confirmed_topup_cannot_be_cancelled(): void
    {
        $topup = WalletTopup::factory()->confirmed()
            ->create(['user_id' => $this->user->getKey()]);

        $response = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.cancel', $topup))
            ->assertStatus(422);

        $this->assertSame('wallet_request_not_pending', $this->errorCode($response));
    }

    public function test_the_topup_list_shows_only_your_own(): void
    {
        WalletTopup::factory()->create(['user_id' => $this->user->getKey()]);
        WalletTopup::factory()->create(['user_id' => $this->activeUser()->getKey()]);

        $this->asUser($this->user)
            ->getJson(route('v1.me.wallet.topups.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ── Penarikan ───────────────────────────────────────────────────────────

    /** Tanpa rekening terverifikasi, saldo tidak bisa keluar. */
    public function test_a_withdrawal_needs_a_verified_bank_account(): void
    {
        $this->fundWallet($this->user, 500_000);

        $response = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 100_000])
            ->assertStatus(422);

        $this->assertSame('bank_account_not_verified', $this->errorCode($response));
        $this->assertSame(500_000, $this->user->fresh()->walletBalance());
    }

    /**
     * Identitas terverifikasi TIDAK membuka penarikan. Keduanya baris
     * verifikasi dengan `type` berbeda, dan yang membuka penarikan hanya
     * rekening.
     */
    public function test_a_verified_identity_alone_does_not_open_withdrawals(): void
    {
        $this->fundWallet($this->user, 500_000);
        $this->verifyIdentity($this->user);

        $response = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 100_000])
            ->assertStatus(422);

        $this->assertSame('bank_account_not_verified', $this->errorCode($response));
    }

    /** SALDONYA LANGSUNG BERKURANG — bukan saat pengelola mencairkan. */
    public function test_requesting_a_withdrawal_holds_the_money_immediately(): void
    {
        $this->fundWallet($this->user, 500_000);
        $this->verifyBankAccount($this->user);

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 200_000])
            ->assertCreated()
            ->assertJsonPath('data.status', WalletWithdrawalStatus::Requested->value)
            ->assertJsonPath('data.awaits_processing', true)
            ->assertJsonPath('data.destination.bank_code', 'BCA');

        $this->assertSame(300_000, $this->user->fresh()->walletBalance());
    }

    /**
     * Dan karena ditahan di muka, saldo yang sama tidak bisa diminta dua kali.
     * Inilah yang dicegah pemotongan di muka.
     */
    public function test_the_same_money_cannot_be_withdrawn_twice(): void
    {
        $this->fundWallet($this->user, 100_000);
        $this->verifyBankAccount($this->user);

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 100_000])
            ->assertCreated();

        $response = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 100_000])
            ->assertStatus(422);

        $this->assertSame('insufficient_balance', $this->errorCode($response));
        $this->assertSame(0, $this->user->fresh()->walletBalance());
    }

    /** Saldo kurang memberi tahu kekurangannya, supaya klien tidak perlu menghitung. */
    public function test_insufficient_balance_names_the_shortfall(): void
    {
        $this->fundWallet($this->user, 60_000);
        $this->verifyBankAccount($this->user);

        $response = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 100_000])
            ->assertStatus(422);

        $this->assertSame(40_000, $response->json('context.shortfall'));
        $this->assertSame(60_000, $response->json('context.balance'));
    }

    /** Permintaan yang gagal tidak meninggalkan baris apa pun. */
    public function test_a_failed_withdrawal_leaves_no_row_behind(): void
    {
        $this->fundWallet($this->user, 60_000);
        $this->verifyBankAccount($this->user);

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 100_000])
            ->assertStatus(422);

        $this->assertSame(0, WalletWithdrawal::query()
            ->where('user_id', $this->user->getKey())->count());
        $this->assertSame(60_000, $this->user->fresh()->walletBalance());
    }

    /** Membatalkan MENGEMBALIKAN tahanannya. Tanpa ini uang orang hilang. */
    public function test_cancelling_a_withdrawal_returns_the_held_money(): void
    {
        $this->fundWallet($this->user, 300_000);
        $this->verifyBankAccount($this->user);

        $id = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 200_000])
            ->assertCreated()
            ->json('data.id');

        $withdrawal = WalletWithdrawal::query()->where('ulid', $id)->firstOrFail();

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.cancel', $withdrawal))
            ->assertOk()
            ->assertJsonPath('data.status', WalletWithdrawalStatus::Cancelled->value);

        $this->assertSame(300_000, $this->user->fresh()->walletBalance());

        // Dua baris, bukan satu yang dihapus: buku besar append-only.
        $this->assertSame(1, $this->user->fresh()->wallet->entries()
            ->where('type', WalletEntryType::WithdrawalReversal)->count());
    }

    public function test_someone_elses_withdrawal_cannot_be_cancelled(): void
    {
        $other = $this->activeUser();
        $this->fundWallet($other, 300_000);
        $this->verifyBankAccount($other);

        $withdrawal = WalletWithdrawal::factory()->create([
            'user_id' => $other->getKey(),
            'verification_id' => $other->verifiedBankAccount()->getKey(),
        ]);

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.cancel', $withdrawal))
            ->assertForbidden();
    }

    public function test_too_many_pending_withdrawals_are_refused(): void
    {
        $max = (int) config('sekarya.wallet.max_pending_requests');
        $this->fundWallet($this->user, 10_000_000);
        $this->verifyBankAccount($this->user);

        for ($i = 0; $i < $max; $i++) {
            $this->asUser($this->user)
                ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 50_000])
                ->assertCreated();
        }

        $response = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 50_000])
            ->assertStatus(422);

        $this->assertSame('too_many_pending_wallet_requests', $this->errorCode($response));
    }

    // ── Uang masuk dari pekerjaan ───────────────────────────────────────────

    /**
     * Pekerja sebagai PENERIMA BAYARAN: upahnya masuk saldo saat dana dilepas.
     * Angkanya harga PER ORANG, bukan total task.
     */
    public function test_an_approved_worker_is_paid_into_their_wallet(): void
    {
        $poster = $this->activeUser();
        $workerA = $this->activeUser();
        $workerB = $this->activeUser();

        $task = Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
            'dealt_at' => now(),
            'workers_needed' => 2,
        ]);
        $this->hireWorker($task, $workerA, 150_000);
        $this->hireWorker($task, $workerB, 250_000);

        Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $poster->getKey(),
            'amount' => 400_000,
        ]);

        $this->openActivities($task, $poster);

        // Semua menyerahkan lebih dulu — status task mengikuti AGREGAT, jadi
        // `submitted` baru tercapai kalau tidak ada lagi yang menggantung.
        $this->submitEverything($task);

        foreach ($task->activities()->get() as $activity) {
            $this->asUser($poster)
                ->postJson(route('v1.activities.approve', $activity))
                ->assertOk();
        }

        // Harga penawarannya sendiri — bukan Rp400.000 untuk masing-masing.
        $this->assertSame(150_000, $workerA->fresh()->walletBalance());
        $this->assertSame(250_000, $workerB->fresh()->walletBalance());
        // Dan pemberi kerja tidak menerima apa pun.
        $this->assertSame(0, $poster->fresh()->walletBalance());
    }

    /** Dilepas SEKALI, saat pekerja terakhir disetujui — bukan pada yang pertama. */
    public function test_nobody_is_paid_until_every_worker_is_approved(): void
    {
        $poster = $this->activeUser();
        $workerA = $this->activeUser();
        $workerB = $this->activeUser();

        $task = Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
            'dealt_at' => now(),
            'workers_needed' => 2,
        ]);
        $this->hireWorker($task, $workerA, 150_000);
        $this->hireWorker($task, $workerB, 250_000);
        Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $poster->getKey(),
            'amount' => 400_000,
        ]);
        $this->openActivities($task, $poster);

        $this->submitEverything($task);

        $first = $task->activities()->where('worker_id', $workerA->getKey())->firstOrFail();
        $this->asUser($poster)
            ->postJson(route('v1.activities.approve', $first))
            ->assertOk();

        $this->assertSame(0, $workerA->fresh()->walletBalance());
        $this->assertSame(0, $workerB->fresh()->walletBalance());
    }

    /** Task batal setelah dana ditahan → uangnya kembali ke SALDO PEMBAYARNYA. */
    public function test_cancelling_a_paid_task_refunds_the_payer(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();

        $task = Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
            'dealt_at' => now(),
        ]);
        $this->hireWorker($task, $worker, 220_000);
        Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $poster->getKey(),
            'amount' => 220_000,
        ]);
        $this->openActivities($task, $poster);

        $this->asUser($poster)
            ->postJson(route('v1.tasks.cancel', $task), ['reason' => 'Rumahnya kebanjiran.'])
            ->assertOk();

        $this->assertSame(PaymentStatus::Refunded, $task->payment()->firstOrFail()->status);
        $this->assertSame(220_000, $poster->fresh()->walletBalance());
        // Dan bukan ke pekerjanya.
        $this->assertSame(0, $worker->fresh()->walletBalance());
    }

    /**
     * Pembatalnya bisa PEKERJA — dan uangnya tetap kembali ke pembayarnya.
     * Mengembalikan ke pembatal akan memindahkan uang pemberi kerja ke orang
     * lain.
     */
    public function test_a_worker_cancelling_still_refunds_the_poster(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();

        $task = Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
            'dealt_at' => now(),
        ]);
        $this->hireWorker($task, $worker, 220_000);
        Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $poster->getKey(),
            'amount' => 220_000,
        ]);
        $this->openActivities($task, $poster);

        $this->asUser($worker)
            ->postJson(route('v1.tasks.cancel', $task), ['reason' => 'Saya sakit, tidak bisa datang.'])
            ->assertOk();

        $this->assertSame(220_000, $poster->fresh()->walletBalance());
        $this->assertSame(0, $worker->fresh()->walletBalance());
    }

    /** Task yang dananya belum pernah ditahan tidak mengembalikan apa pun. */
    public function test_cancelling_an_unpaid_task_refunds_nothing(): void
    {
        $poster = $this->activeUser();
        $task = Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
        ]);
        Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $poster->getKey(),
            'amount' => 220_000,
        ]);

        $this->asUser($poster)
            ->postJson(route('v1.tasks.cancel', $task), ['reason' => 'Tidak jadi.'])
            ->assertOk();

        $this->assertSame(0, $poster->fresh()->walletBalance());
    }

    // ── Akses ───────────────────────────────────────────────────────────────

    public function test_every_wallet_endpoint_needs_a_token(): void
    {
        $this->getJson(route('v1.me.wallet.show'))->assertUnauthorized();
        $this->getJson(route('v1.me.wallet.entries.index'))->assertUnauthorized();
        $this->postJson(route('v1.me.wallet.topups.store'), ['amount' => 50_000])
            ->assertUnauthorized();
        $this->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 50_000])
            ->assertUnauthorized();
    }

    /** Token long_lived tidak boleh memanggil endpoint aplikasi. */
    public function test_a_long_lived_token_cannot_read_the_balance(): void
    {
        $this->asUserWithLongLived($this->user)
            ->getJson(route('v1.me.wallet.show'))
            ->assertForbidden();
    }

    /** Pengelola punya populasi token sendiri; tokennya ditolak di sini. */
    public function test_an_admin_token_is_refused_on_user_wallet_endpoints(): void
    {
        $this->asAdmin($this->activeAdmin())
            ->getJson(route('v1.me.wallet.show'))
            ->assertUnauthorized();
    }

    /**
     * Setiap pekerja mulai lalu menyerahkan hasilnya, lewat jalur nyata.
     *
     * Semua menyerahkan SEBELUM ada yang disetujui: status task mengikuti
     * agregat, dan `active -> completed` bukan perpindahan yang sah.
     */
    private function submitEverything(Task $task): void
    {
        foreach ($task->activities()->with('worker')->get() as $activity) {
            $this->asUser($activity->worker)
                ->postJson(route('v1.activities.start', $activity))
                ->assertOk();
            $this->asUser($activity->worker)
                ->postJson(route('v1.activities.submit', $activity), ['worker_note' => 'beres'])
                ->assertOk();
        }
    }
}
