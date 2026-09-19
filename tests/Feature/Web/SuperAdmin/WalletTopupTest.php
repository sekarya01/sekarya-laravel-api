<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Enums\WalletTopupStatus;
use App\Models\WalletTopup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Antrean isi saldo lewat web: pengguna melapor → pengelola konfirmasi/tolak.
 */
final class WalletTopupTest extends TestCase
{
    use RefreshDatabase;

    private function reportedTopup(int $amount = 250_000): WalletTopup
    {
        return WalletTopup::factory()->create([
            'user_id' => $this->activeUser()->getKey(),
            'amount' => $amount,
            'sender_note' => 'BCA a.n. Budi',
        ]);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('super_admin.wallet_topups.index'))
            ->assertRedirect(route('super_admin.login'));
    }

    public function test_antrean_menampilkan_isi_saldo_yang_menunggu(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $topup = $this->reportedTopup();

        $this->get(route('super_admin.wallet_topups.index'))
            ->assertOk()
            ->assertSee('250.000', false)
            ->assertSee($topup->user->email, false);
    }

    public function test_dasbor_menghitung_isi_saldo_yang_menunggu(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $this->reportedTopup();

        $this->get(route('super_admin.dashboard'))
            ->assertOk()
            ->assertSee('Isi saldo menunggu', false);
    }

    public function test_mengonfirmasi_menambah_saldo_dan_tercatat_di_audit(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $topup = $this->reportedTopup();

        $this->get(route('super_admin.wallet_topups.show', $topup->ulid))
            ->assertOk()
            ->assertSee('Konfirmasi & tambah saldo', false);

        $this->post(route('super_admin.wallet_topups.confirm', $topup->ulid))
            ->assertRedirect(route('super_admin.wallet_topups.show', $topup->ulid))
            ->assertSessionHas('status');

        $this->assertSame(WalletTopupStatus::Confirmed, $topup->refresh()->status);
        $this->assertSame(250_000, (int) $topup->user->fresh()->walletOrNew()->balance);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'wallet_topup.confirmed',
            'subject_id' => $topup->getKey(),
        ]);
    }

    public function test_mengonfirmasi_dua_kali_tidak_menggandakan_saldo(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $topup = $this->reportedTopup();

        $this->post(route('super_admin.wallet_topups.confirm', $topup->ulid))->assertSessionHas('status');
        $this->post(route('super_admin.wallet_topups.confirm', $topup->ulid))->assertSessionHasErrors('action');

        $this->assertSame(250_000, (int) $topup->user->fresh()->walletOrNew()->balance);
    }

    public function test_menolak_tidak_mengubah_saldo(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $topup = $this->reportedTopup();

        $this->post(route('super_admin.wallet_topups.reject', $topup->ulid), [
            'reason' => 'Tidak ada mutasi masuk sejumlah itu hari ini.',
        ])->assertSessionHas('status');

        $topup->refresh();
        $this->assertSame(WalletTopupStatus::Rejected, $topup->status);
        $this->assertNotNull($topup->rejection_reason);
        $this->assertSame(0, (int) $topup->user->fresh()->walletOrNew()->balance);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'wallet_topup.rejected',
            'subject_id' => $topup->getKey(),
        ]);
    }

    public function test_alasan_penolakan_wajib_cukup_panjang(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $topup = $this->reportedTopup();

        $this->post(route('super_admin.wallet_topups.reject', $topup->ulid), ['reason' => 'tidak'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(WalletTopupStatus::AwaitingConfirmation, $topup->refresh()->status);
    }
}
