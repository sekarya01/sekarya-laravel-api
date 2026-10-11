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

    public function test_create_menyimpan_wilayah(): void
    {
        $res = $this->asAdmin($this->admin)->postJson(
            route('v1.admin.worker-invites.store'),
            ['max_uses' => 3, 'city' => 'Bandung', 'province' => 'Jawa Barat'],
        );

        $res->assertCreated()
            ->assertJsonPath('data.city', 'Bandung')
            ->assertJsonPath('data.province', 'Jawa Barat')
            ->assertJsonPath('data.area_label', 'Bandung, Jawa Barat');

        $this->assertDatabaseHas('worker_invite_codes', [
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
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

    /**
     * Kontrak (OpenAPI) menjanjikan cursor + `per_page`; dulu kodenya
     * `paginate(20)` — `per_page` diabaikan dan `meta` berbentuk lain.
     */
    public function test_daftar_kode_memakai_cursor_dan_per_page(): void
    {
        WorkerInviteCode::factory()->count(3)->create();

        $first = $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.worker-invites.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['meta' => ['next_cursor', 'per_page']]);

        $second = $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.worker-invites.index', ['per_page' => 2, 'cursor' => $first->json('meta.next_cursor')]))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $ids = [...array_column($first->json('data'), 'id'), ...array_column($second->json('data'), 'id')];
        $this->assertCount(3, array_unique($ids));
    }

    public function test_daftar_pemakai_memakai_cursor(): void
    {
        $code = WorkerInviteCode::factory()->create();

        $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.worker-invites.redemptions.index', [$code->getKey(), 'per_page' => 5]))
            ->assertOk()
            ->assertJsonStructure(['meta' => ['next_cursor', 'per_page']]);
    }

    /**
     * API, dasbor, dan CLI memakai CreateWorkerInviteCodeData yang SAMA:
     * catatan di-trim di satu tempat (dulu hanya dasbor yang men-trim), dan
     * respons API tetap membawa `plain_code` + `message`.
     */
    public function test_semua_jalur_penerbitan_menormalkan_catatan_yang_sama(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.worker-invites.store'), ['max_uses' => 2, 'note' => '  promo  '])
            ->assertCreated()
            ->assertJsonPath('data.note', 'promo')
            ->assertJsonPath('message', 'Kode undangan mitra dibuat. Kodenya tampil terus di menu Kode Mitra.')
            ->assertJsonStructure(['data' => ['plain_code', 'code']]);

        $this->artisan('sekarya:worker-code', ['--max-uses' => 3, '--note' => '  dari cli  '])->assertSuccessful();

        $cli = WorkerInviteCode::query()->where('max_uses', 3)->firstOrFail();
        $this->assertSame('dari cli', $cli->note);
        $this->assertNull($cli->created_by_admin_id);
    }
}
