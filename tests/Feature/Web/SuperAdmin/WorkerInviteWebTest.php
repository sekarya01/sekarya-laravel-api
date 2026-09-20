<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Enums\UserActiveMode;
use App\Models\User;
use App\Models\WorkerInviteCode;
use App\Support\WorkerInviteCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Menu Kode Mitra: daftar, generate (kode dibuat server, tampil sekali),
 * detail pemakai, dan nonaktifkan.
 */
final class WorkerInviteWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_menampilkan_kode_tanpa_hash(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        WorkerInviteCode::factory()->create(['note' => 'batch bandung']);

        $this->get(route('super_admin.worker_invites.index'))
            ->assertOk()
            ->assertSee('batch bandung', false)
            ->assertDontSee('code_hash', false);
    }

    public function test_generate_membuat_kode_dan_menampilkannya_sekali(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        $res = $this->post(route('super_admin.worker_invites.store'), [
            'max_uses' => 10,
            'expires_at' => now()->addDays(7)->format('Y-m-d\TH:i'),
            'note' => 'perekrutan',
        ]);

        $code = WorkerInviteCode::query()->firstOrFail();
        $res->assertRedirect(route('super_admin.worker_invites.show', $code->getKey()));
        $this->assertSame(10, $code->max_uses);

        // Plain tampil di halaman detail sesudah generate…
        $plain = session('plain_code');
        $this->assertIsString($plain);
        $this->assertTrue(WorkerInviteCodeGenerator::isWellFormed($plain));
        $this->get(route('super_admin.worker_invites.show', $code->getKey()))
            ->assertOk()
            ->assertSee($plain, false);

        // …tapi hilang saat dimuat ulang (flash sekali).
        $this->get(route('super_admin.worker_invites.show', $code->getKey()))
            ->assertOk()
            ->assertDontSee($plain, false);
    }

    public function test_generate_menolak_tanpa_masa_hidup_yang_jelas(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');

        // max_uses wajib — kode tanpa batas hit adalah kode abadi.
        $this->post(route('super_admin.worker_invites.store'), ['note' => 'x'])
            ->assertSessionHasErrors('max_uses');

        $this->assertSame(0, WorkerInviteCode::query()->count());
    }

    public function test_detail_menampilkan_pekerja_yang_memakai(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $plain = WorkerInviteCodeGenerator::generate();
        $code = WorkerInviteCode::factory()->create([
            'code_hash' => WorkerInviteCode::hash($plain),
            'prefix' => mb_substr($plain, 0, 2),
        ]);

        $user = User::factory()->create(['active_mode' => UserActiveMode::Hiring]);
        Sanctum::actingAs($user, ['token:access']);
        $this->postJson('/api/v1/me/worker/redeem', ['code' => $plain])->assertOk();

        $this->actingAs($this->superAdmin(), 'admin_web');
        $this->get(route('super_admin.worker_invites.show', $code->getKey()))
            ->assertOk()
            ->assertSee($user->name, false)
            ->assertSee($user->email, false);
    }

    public function test_nonaktifkan_mematikan_kode(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $code = WorkerInviteCode::factory()->create();

        $this->post(route('super_admin.worker_invites.deactivate', $code->getKey()))
            ->assertRedirect(route('super_admin.worker_invites.show', $code->getKey()));

        $this->assertFalse($code->fresh()->is_active);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'worker_invite.deactivated',
            'subject_id' => $code->getKey(),
        ]);
    }
}
