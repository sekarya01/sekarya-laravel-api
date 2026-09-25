<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\AdminAction;
use App\Enums\WalletEntryType;
use App\Enums\WalletTopupStatus;
use App\Enums\WalletWithdrawalStatus;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletWithdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dua antrean manual yang menyentuh uang sungguhan.
 *
 * `POST /admin/wallet/topups/{topup}/confirm` adalah SATU-SATUNYA jalan saldo
 * bisa bertambah dari isi ulang — sederajat dengan `payments/{payment}/confirm`
 * sebagai satu-satunya jalan menuju `held`.
 */
final class AdminWalletApiTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->admin = $this->activeAdmin();
        $this->user = $this->activeUser();
    }

    private function pendingTopup(int $amount = 250_000): WalletTopup
    {
        return WalletTopup::factory()->create([
            'user_id' => $this->user->getKey(),
            'amount' => $amount,
        ]);
    }

    private function requestedWithdrawal(int $amount = 200_000): WalletWithdrawal
    {
        $this->fundWallet($this->user, $amount);
        $this->verifyBankAccount($this->user);

        $id = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => $amount])
            ->assertCreated()
            ->json('data.id');

        return WalletWithdrawal::query()->where('ulid', $id)->firstOrFail();
    }

    // ── Antrean isi saldo ───────────────────────────────────────────────────

    /** Bawaannya HANYA yang menunggu tindakan manusia. */
    public function test_the_topup_queue_shows_only_what_awaits_a_decision(): void
    {
        $this->pendingTopup();
        WalletTopup::factory()->confirmed()->create(['user_id' => $this->user->getKey()]);

        $this->asAdmin($this->admin)->getJson(route('v1.admin.wallet.topups.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.awaits_confirmation', true);
    }

    /** Statusnya bisa disaring — untuk meninjau keputusan yang sudah diambil. */
    public function test_the_topup_queue_can_be_filtered_by_status(): void
    {
        $this->pendingTopup();
        WalletTopup::factory()->confirmed()->create(['user_id' => $this->user->getKey()]);

        $this->asAdmin($this->admin)->getJson(route('v1.admin.wallet.topups.index', [
            'status' => WalletTopupStatus::Confirmed->value,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', WalletTopupStatus::Confirmed->value);
    }

    /** Antrean menyebut petunjuk pengirimnya — tanpa itu ia hanya bisa ditebak. */
    public function test_the_queue_carries_the_sender_note(): void
    {
        WalletTopup::factory()->create([
            'user_id' => $this->user->getKey(),
            'sender_note' => 'BCA 1234 a.n. Budi',
        ]);

        $this->asAdmin($this->admin)->getJson(route('v1.admin.wallet.topups.index'))
            ->assertOk()
            ->assertJsonPath('data.0.sender_note', 'BCA 1234 a.n. Budi');
    }

    // ── Konfirmasi ──────────────────────────────────────────────────────────

    public function test_confirming_a_topup_is_what_adds_the_balance(): void
    {
        $topup = $this->pendingTopup(250_000);

        $this->assertSame(0, $this->user->fresh()->walletBalance());

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.confirm', $topup))
            ->assertOk()
            ->assertJsonPath('data.status', WalletTopupStatus::Confirmed->value);

        $this->assertSame(250_000, $this->user->fresh()->walletBalance());
    }

    /** Dan meninggalkan satu baris buku besar yang menjelaskan sebabnya. */
    public function test_a_confirmed_topup_leaves_a_ledger_entry(): void
    {
        $topup = $this->pendingTopup(250_000);

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.confirm', $topup))
            ->assertOk();

        $entry = $this->user->fresh()->wallet->entries()->firstOrFail();

        $this->assertSame(WalletEntryType::Topup, $entry->type);
        $this->assertSame(250_000, $entry->amount);
        $this->assertSame(250_000, $entry->balance_after);
        $this->assertSame('wallet_topups', $entry->reference_type);
        $this->assertSame((int) $topup->getKey(), (int) $entry->reference_id);
    }

    /**
     * Tombol yang tertekan dua kali tidak boleh menggandakan uang.
     *
     * Kegagalannya keluar sebagai aturan bisnis (`wallet_request_not_pending`),
     * bukan sebagai #1062 — indeks unique tetap ada di belakangnya sebagai
     * pengaman terakhir, tapi pengguna tidak boleh membaca galat basis data.
     */
    public function test_confirming_twice_does_not_double_the_money(): void
    {
        $topup = $this->pendingTopup(250_000);

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.confirm', $topup))->assertOk();

        $response = $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.confirm', $topup))
            ->assertStatus(422);

        $this->assertSame('wallet_request_not_pending', $this->errorCode($response));
        $this->assertSame(250_000, $this->user->fresh()->walletBalance());
    }

    public function test_a_cancelled_topup_cannot_be_confirmed(): void
    {
        $topup = $this->pendingTopup();
        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.topups.cancel', $topup))->assertOk();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.confirm', $topup))
            ->assertStatus(422);

        $this->assertSame(0, $this->user->fresh()->walletBalance());
    }

    public function test_confirming_is_recorded_in_the_audit_trail(): void
    {
        $topup = $this->pendingTopup(250_000);

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.confirm', $topup))->assertOk();

        $log = AdminAuditLog::query()
            ->where('admin_id', $this->admin->getKey())
            ->where('action', AdminAction::WalletTopupConfirmed)
            ->firstOrFail();

        $this->assertSame('wallet_topup', $log->subject_type);
        $this->assertSame((int) $topup->getKey(), (int) $log->subject_id);
    }

    // ── Penolakan isi saldo ─────────────────────────────────────────────────

    public function test_rejecting_a_topup_adds_nothing_and_needs_a_reason(): void
    {
        $topup = $this->pendingTopup();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.reject', $topup), ['reason' => 'pendek'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.reject', $topup), [
                'reason' => 'Tidak ada mutasi masuk dengan nominal itu.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', WalletTopupStatus::Rejected->value);

        $this->assertSame(0, $this->user->fresh()->walletBalance());
    }

    /** Alasannya dibaca penggunanya sendiri, bukan hanya tersimpan di jejak audit. */
    public function test_the_rejection_reason_reaches_the_user(): void
    {
        $topup = $this->pendingTopup();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.reject', $topup), [
                'reason' => 'Tidak ada mutasi masuk dengan nominal itu.',
            ])->assertOk();

        $this->asUser($this->user)->getJson(route('v1.me.wallet.topups.index'))
            ->assertOk()
            ->assertJsonPath(
                'data.0.rejection_reason',
                'Tidak ada mutasi masuk dengan nominal itu.',
            );
    }

    /** Penolakannya FINAL — berbeda dari penolakan pembayaran task. */
    public function test_a_rejected_topup_cannot_be_confirmed_afterwards(): void
    {
        $topup = $this->pendingTopup();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.reject', $topup), [
                'reason' => 'Tidak ada mutasi masuk dengan nominal itu.',
            ])->assertOk();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.topups.confirm', $topup))
            ->assertStatus(422);

        $this->assertSame(0, $this->user->fresh()->walletBalance());
    }

    // ── Antrean pencairan ───────────────────────────────────────────────────

    public function test_the_withdrawal_queue_shows_what_awaits_transfer(): void
    {
        $this->requestedWithdrawal();

        $this->asAdmin($this->admin)->getJson(route('v1.admin.wallet.withdrawals.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.awaits_processing', true)
            ->assertJsonPath('data.0.destination.bank_code', 'BCA');
    }

    /**
     * NOMOR REKENING TIDAK KELUAR DI SINI.
     *
     * Ia terbaca satu layar lebih jauh, di detail verifikasi, dan pembacaan
     * di sana dicatat. Antrean ini secara harfiah tidak punya kode untuk
     * mengeluarkannya — pola yang sama dengan antrean verifikasi dan NIK.
     */
    public function test_the_withdrawal_queue_never_leaks_the_account_number(): void
    {
        $this->requestedWithdrawal();

        $body = $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.wallet.withdrawals.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('1234567890', $body);
        // Kunci nomor UTUH (maupun kolom terenkripsinya) tidak ada. Yang boleh
        // hanya empat digit terakhir di `account_number_masked`.
        $this->assertStringNotContainsString('"account_number"', $body);
        $this->assertStringNotContainsString('account_number_enc', $body);
        $this->assertStringNotContainsString('account_number_last4', $body);
        $this->assertSame(
            ['verification_id', 'bank_code', 'account_holder_name', 'account_number_masked'],
            array_keys((array) json_decode($body, true)['data'][0]['destination']),
        );
    }

    /** Menyelesaikan TIDAK memotong saldo lagi — sudah ditahan sejak diminta. */
    public function test_completing_a_withdrawal_does_not_deduct_again(): void
    {
        $withdrawal = $this->requestedWithdrawal(200_000);

        $this->assertSame(0, $this->user->fresh()->walletBalance());

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.withdrawals.complete', $withdrawal), [
                'transfer_reference' => 'TRX-99887766',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', WalletWithdrawalStatus::Completed->value)
            ->assertJsonPath('data.transfer_reference', 'TRX-99887766');

        $this->assertSame(0, $this->user->fresh()->walletBalance());
        $this->assertSame(2, $this->user->fresh()->wallet->entries()->count());
    }

    public function test_completing_twice_is_refused(): void
    {
        $withdrawal = $this->requestedWithdrawal();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.withdrawals.complete', $withdrawal))->assertOk();

        $response = $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.withdrawals.complete', $withdrawal))
            ->assertStatus(422);

        $this->assertSame('wallet_request_not_pending', $this->errorCode($response));
    }

    /**
     * Menolak MENGEMBALIKAN tahanannya.
     *
     * Tanpa ini, penolakan pengelola menghapus uang orang tanpa uang itu
     * pernah keluar ke rekening mana pun — dan tidak ada galat yang
     * menandainya.
     */
    public function test_rejecting_a_withdrawal_returns_the_held_money(): void
    {
        $withdrawal = $this->requestedWithdrawal(200_000);

        $this->assertSame(0, $this->user->fresh()->walletBalance());

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.withdrawals.reject', $withdrawal), [
                'reason' => 'Nama pemilik rekening tidak cocok dengan KTP.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', WalletWithdrawalStatus::Rejected->value);

        $this->assertSame(200_000, $this->user->fresh()->walletBalance());

        $entry = $this->user->fresh()->wallet->entries()
            ->where('type', WalletEntryType::WithdrawalReversal)->firstOrFail();
        $this->assertSame(200_000, $entry->amount);
    }

    public function test_rejecting_a_withdrawal_needs_a_reason(): void
    {
        $withdrawal = $this->requestedWithdrawal();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.withdrawals.reject', $withdrawal), ['reason' => 'x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    /** Yang sudah dicairkan tidak bisa ditolak — uangnya sudah keluar. */
    public function test_a_completed_withdrawal_cannot_be_rejected(): void
    {
        $withdrawal = $this->requestedWithdrawal(200_000);

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.withdrawals.complete', $withdrawal))->assertOk();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.withdrawals.reject', $withdrawal), [
                'reason' => 'Berubah pikiran setelah transfer terkirim.',
            ])
            ->assertStatus(422);

        $this->assertSame(0, $this->user->fresh()->walletBalance());
    }

    /** Dan yang sudah dibatalkan penggunanya juga tidak bisa dicairkan. */
    public function test_a_cancelled_withdrawal_cannot_be_completed(): void
    {
        $withdrawal = $this->requestedWithdrawal(200_000);

        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.cancel', $withdrawal))->assertOk();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.withdrawals.complete', $withdrawal))
            ->assertStatus(422);

        // Tahanannya sudah kembali sekali, dan hanya sekali.
        $this->assertSame(200_000, $this->user->fresh()->walletBalance());
    }

    public function test_processing_a_withdrawal_is_recorded_in_the_audit_trail(): void
    {
        $withdrawal = $this->requestedWithdrawal();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.wallet.withdrawals.complete', $withdrawal))->assertOk();

        $log = AdminAuditLog::query()
            ->where('admin_id', $this->admin->getKey())
            ->where('action', AdminAction::WalletWithdrawalCompleted)
            ->firstOrFail();

        $this->assertSame('wallet_withdrawal', $log->subject_type);
    }

    // ── Akses ───────────────────────────────────────────────────────────────

    /** Token pengguna tidak sah di /admin — populasi tokennya berbeda. */
    public function test_a_user_token_cannot_reach_the_wallet_queues(): void
    {
        $this->asUser($this->user)
            ->getJson(route('v1.admin.wallet.topups.index'))
            ->assertUnauthorized();

        $this->asUser($this->user)
            ->getJson(route('v1.admin.wallet.withdrawals.index'))
            ->assertUnauthorized();
    }

    public function test_the_wallet_queues_need_a_token(): void
    {
        $this->getJson(route('v1.admin.wallet.topups.index'))->assertUnauthorized();
        $this->getJson(route('v1.admin.wallet.withdrawals.index'))->assertUnauthorized();
    }

    /**
     * Pengelola biasa boleh — ini bukan kewenangan super_admin.
     *
     * Konfirmasi transfer memang pekerjaan sehari-hari pengelola; yang
     * dikunci super_admin hanya pengelolaan akun pengelola.
     */
    public function test_a_plain_admin_may_decide_wallet_requests(): void
    {
        $topup = $this->pendingTopup();

        $this->asAdmin($this->activeAdmin())
            ->postJson(route('v1.admin.wallet.topups.confirm', $topup))
            ->assertOk();
    }
}
