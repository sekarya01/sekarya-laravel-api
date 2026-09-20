<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\UserActiveMode;
use App\Models\Admin;
use App\Models\User;
use App\Models\WorkerInviteCode;
use App\Support\WorkerInviteCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * API pengelola untuk kode undangan mitra: terbitkan (plain sekali),
 * daftar (tanpa hash), detail, nonaktifkan, dan siapa memakainya.
 */
final class WorkerInviteAdminApiTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->admin = $this->activeAdmin();
    }

    public function test_create_mengembalikan_plain_sekali_dan_menyimpan_hash(): void
    {
        $res = $this->asAdmin($this->admin)->postJson(
            route('v1.admin.worker-invites.store'),
            ['max_uses' => 5, 'note' => 'batch uji'],
        );

        $res->assertCreated()
            ->assertJsonPath('data.max_uses', 5)
            ->assertJsonMissingPath('data.code_hash');

        $plain = $res->json('data.plain_code');
        $this->assertSame($plain, $res->json('data.code'));
        $this->assertSame(8, mb_strlen((string) $plain));
        $this->assertTrue(WorkerInviteCodeGenerator::isWellFormed((string) $plain));

        $this->assertDatabaseHas('worker_invite_codes', [
            'code_hash' => WorkerInviteCode::hash((string) $plain),
            'max_uses' => 5,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'worker_invite.created',
            'admin_id' => $this->admin->getKey(),
        ]);
    }

    public function test_list_tidak_pernah_memuat_hash(): void
    {
        WorkerInviteCode::factory()->count(2)->create();

        $res = $this->asAdmin($this->admin)->getJson(route('v1.admin.worker-invites.index'));

        $res->assertOk();
        $this->assertCount(2, $res->json('data'));
        $this->assertArrayNotHasKey('code_hash', $res->json('data.0'));
        $this->assertSame(8, mb_strlen((string) $res->json('data.0.code')));
    }

    public function test_deactivate_mematikan_dan_mencatat_audit(): void
    {
        $plain = WorkerInviteCodeGenerator::generate();
        $code = WorkerInviteCode::factory()->create([
            'code_hash' => WorkerInviteCode::hash($plain),
        ]);

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.worker-invites.deactivate', $code->getKey()))
            ->assertOk()
            ->assertJsonPath('data.is_active', false)
            ->assertJsonPath('data.is_usable', false);

        // Idempoten: kedua kali tetap sukses.
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.worker-invites.deactivate', $code->getKey()))
            ->assertOk();

        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'worker_invite.deactivated',
            'subject_id' => $code->getKey(),
        ]);

        // Kode mati tidak bisa ditukar lagi.
        $user = User::factory()->create(['active_mode' => UserActiveMode::Hiring]);
        Sanctum::actingAs($user, ['token:access']);
        $this->postJson('/api/v1/me/worker/redeem', ['code' => $plain])
            ->assertStatus(422)->assertJsonPath('code', 'worker_invite_inactive');
    }

    public function test_redemptions_menampilkan_pemakai_dan_status_ktp(): void
    {
        $plain = WorkerInviteCodeGenerator::generate();
        $code = WorkerInviteCode::factory()->create([
            'code_hash' => WorkerInviteCode::hash($plain),
        ]);

        $user = User::factory()->create(['active_mode' => UserActiveMode::Hiring]);
        Sanctum::actingAs($user, ['token:access']);
        $this->postJson('/api/v1/me/worker/redeem', ['code' => $plain])->assertOk();

        $res = $this->asAdmin($this->admin)->getJson(
            route('v1.admin.worker-invites.redemptions.index', $code->getKey()),
        );

        $res->assertOk()
            ->assertJsonPath('data.0.user_id', $user->getKey())
            ->assertJsonPath('data.0.email', $user->email)
            ->assertJsonPath('data.0.identity_verified', false);
    }
}
