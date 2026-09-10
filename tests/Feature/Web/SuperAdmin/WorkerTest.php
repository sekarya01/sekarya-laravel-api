<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Enums\Gender;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\UserWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Direktori dan detail pekerja: gerbang siap-kerja yang sama dengan API,
 * plus kartu verifikasi identitas di halaman detail.
 */
final class WorkerTest extends TestCase
{
    use RefreshDatabase;

    private function readyWorker(): User
    {
        $user = User::factory()->withWorkerProfile()->create();
        $user->status = UserStatus::Active;
        $user->email_verified_at = now();
        $user->save();
        $this->verifyIdentity($user);

        return $user->refresh();
    }

    public function test_direktori_menampilkan_pekerja_siap(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->readyWorker();

        $this->get(route('super_admin.workers.index'))
            ->assertOk()
            ->assertSee($user->workerProfile->resolvedName(), false);
    }

    public function test_filter_belum_siap_menyembunyikan_yang_siap(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $ready = $this->readyWorker();
        $notReady = User::factory()->withWorkerProfile()->create();
        $notReady->status = UserStatus::Active;
        $notReady->email_verified_at = now();
        $notReady->save();

        $response = $this->get(route('super_admin.workers.index', ['ready_to_work' => '0']))
            ->assertOk();

        $response->assertSee($notReady->workerProfile->resolvedName(), false);
        $response->assertDontSee($ready->workerProfile->resolvedName(), false);
    }

    public function test_detail_memuat_kartu_verifikasi_dan_mencatat_baca(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin_web');
        $user = $this->activeUser();
        $worker = UserWorker::factory()->create(['user_id' => $user->getKey()]);
        $verification = UserVerification::factory()->create(['user_id' => $user->getKey()]);

        $this->get(route('super_admin.workers.show', $worker))
            ->assertOk()
            ->assertSee('Verifikasi identitas', false)
            ->assertSee($verification->document_number_enc, false);

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'verification.viewed',
            'subject_id' => $verification->getKey(),
            'admin_id' => $admin->getKey(),
        ]);
    }

    public function test_detail_tanpa_pengajuan_menjelaskan_syarat(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $user = $this->activeUser();
        $worker = UserWorker::factory()->create(['user_id' => $user->getKey()]);

        $this->get(route('super_admin.workers.show', $worker))
            ->assertOk()
            ->assertSee('belum mengajukan', false);
    }

    public function test_tiap_saring_berdiri_sendiri(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $ready = $this->readyWorker();
        $ready->forceFill(['city' => 'KotaUji', 'gender' => Gender::Male])->save();

        $name = $ready->refresh()->workerProfile->resolvedName();

        // Kesiapan saja.
        $this->get(route('super_admin.workers.index', ['ready_to_work' => '1']))
            ->assertOk()
            ->assertSee($name, false);

        // Gender saja — yang berlawanan jenisnya hilang.
        $this->get(route('super_admin.workers.index', ['gender' => 'male']))
            ->assertOk()
            ->assertSee($name, false);

        $this->get(route('super_admin.workers.index', ['gender' => 'female']))
            ->assertOk()
            ->assertDontSee($name, false);

        // Kota saja (warisan dari akun).
        $this->get(route('super_admin.workers.index', ['city' => 'KotaUji']))
            ->assertOk()
            ->assertSee($name, false);

        // Provinsi yang salah — satu filter cukup untuk mengosongkan.
        $this->get(route('super_admin.workers.index', ['province' => 'ProvinsiUji']))
            ->assertOk()
            ->assertDontSee($name, false);
    }

    public function test_akun_ditangguhkan_tidak_masuk_direktori(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $suspended = $this->activeUser(['email' => 'tahan@sekarya.test']);
        UserWorker::factory()->create(['user_id' => $suspended->getKey()]);
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();

        // Basisnya akun aktif (seperti API) — moderasinya lewat halaman Pengguna.
        $this->get(route('super_admin.workers.index'))
            ->assertOk()
            ->assertDontSee($suspended->refresh()->workerProfile->resolvedName(), false);
    }
}
