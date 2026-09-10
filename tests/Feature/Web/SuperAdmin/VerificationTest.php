<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Enums\VerificationStatus;
use App\Models\UserVerification;
use App\Models\UserWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Antrean verifikasi lewat web: daftar tanpa NIK, detail mencatat jejak
 * baca, putusan lewat endpoint-nya masing-masing.
 */
final class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_antrean_tidak_memuat_nik(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $verification = UserVerification::factory()->create();

        $this->get(route('super_admin.verifications.index'))
            ->assertOk()
            ->assertSee($verification->user->email)
            ->assertDontSee($verification->document_number_enc);
    }

    public function test_detail_mencatat_jejak_baca(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin_web');
        $verification = UserVerification::factory()->create();

        $this->get(route('super_admin.verifications.show', $verification))
            ->assertOk()
            ->assertSee($verification->document_number_enc);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'verification.viewed',
            'subject_id' => $verification->getKey(),
            'admin_id' => $admin->getKey(),
        ]);
    }

    public function test_menyetujui(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin_web');
        $verification = UserVerification::factory()->create();

        $this->post(route('super_admin.verifications.approve', $verification))
            ->assertRedirect(route('super_admin.verifications.show', $verification))
            ->assertSessionHas('status');

        $this->assertSame(
            VerificationStatus::Verified,
            $verification->refresh()->status,
        );
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'verification.approved',
            'subject_id' => $verification->getKey(),
        ]);
    }

    public function test_menolak_wajib_alasan(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $verification = UserVerification::factory()->create();

        $this->post(route('super_admin.verifications.reject', $verification), ['reason' => 'x'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(
            VerificationStatus::Pending,
            $verification->refresh()->status,
        );
    }

    public function test_menolak_dengan_alasan(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $verification = UserVerification::factory()->create();

        $this->post(route('super_admin.verifications.reject', $verification), [
            'reason' => 'Foto KTP tidak terbaca, silakan unggah ulang.',
        ])->assertSessionHas('status');

        $this->assertSame(
            VerificationStatus::Rejected,
            $verification->refresh()->status,
        );
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'verification.rejected',
            'subject_id' => $verification->getKey(),
        ]);
    }

    public function test_mencabut_verifikasi_yang_sudah_diberikan(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $verification = UserVerification::factory()->verified()->create();

        $this->post(route('super_admin.verifications.revoke', $verification), [
            'reason' => 'Identitas terbukti palsu berdasarkan cek silang.',
        ])->assertSessionHas('status');

        $this->assertSame(
            VerificationStatus::Revoked,
            $verification->refresh()->status,
        );
    }

    public function test_putusan_dari_halaman_pekerja_kembali_ke_sana(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();
        $worker = UserWorker::factory()->create(['user_id' => $user->getKey()]);
        $verification = UserVerification::factory()->create(['user_id' => $user->getKey()]);

        $back = '/access/super_admin/workers/'.$worker->getKey();

        $this->post(route('super_admin.verifications.approve', $verification), [
            'redirect_to' => $back,
        ])->assertRedirect($back);
    }

    public function test_saring_status_dan_jenis(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $pendingUser = $this->activeUser(['email' => 'tunggu@sekarya.test']);
        $verifiedUser = $this->activeUser(['email' => 'lolos@sekarya.test']);
        UserVerification::factory()->create(['user_id' => $pendingUser->getKey()]);
        UserVerification::factory()->verified()->create(['user_id' => $verifiedUser->getKey()]);

        $this->get(route('super_admin.verifications.index', ['status' => 'verified', 'type' => 'identity']))
            ->assertOk()
            ->assertSee('lolos@sekarya.test', false)
            ->assertDontSee('tunggu@sekarya.test', false);
    }

    public function test_menyetujui_yang_sudah_disetujui_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $verification = UserVerification::factory()->verified()->create();

        $this->post(route('super_admin.verifications.approve', $verification))
            ->assertSessionHasErrors('action');
    }

    public function test_menolak_yang_sudah_disetujui_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $verification = UserVerification::factory()->verified()->create();

        $this->post(route('super_admin.verifications.reject', $verification), [
            'reason' => 'Alasan yang cukup panjang.',
        ])->assertSessionHasErrors('action');
    }

    public function test_mencabut_yang_masih_menunggu_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $verification = UserVerification::factory()->create();

        $this->post(route('super_admin.verifications.revoke', $verification), [
            'reason' => 'Alasan yang cukup panjang.',
        ])->assertSessionHasErrors('action');
    }

    public function test_redirect_to_luar_dasbor_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $verification = UserVerification::factory()->create();

        $this->post(route('super_admin.verifications.approve', $verification), [
            'redirect_to' => 'https://evil.example/phish',
        ])->assertRedirect(route('super_admin.verifications.show', $verification));
    }
}
