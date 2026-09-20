<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\User;

use App\Enums\UserActiveMode;
use App\Models\User;
use App\Models\WorkerInviteCode;
use App\Support\WorkerInviteCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkerInviteCodeApiTest extends TestCase
{
    use RefreshDatabase;

    private function auth(User $user): void
    {
        Sanctum::actingAs($user, ['token:access']);
    }

    private function makeCode(string $plain, array $over = []): WorkerInviteCode
    {
        return WorkerInviteCode::factory()->create([
            'code_hash' => WorkerInviteCode::hash($plain),
            'prefix' => mb_substr($plain, 0, 2),
            ...$over,
        ]);
    }

    public function test_redeem_ok_membuat_profil_pekerja(): void
    {
        $user = User::factory()->create(['active_mode' => UserActiveMode::Hiring]);
        $this->auth($user);
        $plain = WorkerInviteCodeGenerator::generate();
        $this->makeCode($plain, ['max_uses' => 5]);

        $res = $this->postJson('/api/v1/me/worker/redeem', ['code' => $plain]);

        $res->assertOk();
        $this->assertDatabaseHas('user_workers', ['user_id' => $user->id]);
        $this->assertSame(UserActiveMode::Working->value, $user->fresh()->active_mode->value);
        $this->assertSame(1, WorkerInviteCode::first()->fresh()->used_count);
    }

    public function test_redeem_ditolak_bila_kuota_habis(): void
    {
        $user = User::factory()->create();
        $this->auth($user);
        $plain = WorkerInviteCodeGenerator::generate();
        $this->makeCode($plain, ['max_uses' => 2, 'used_count' => 2]);

        $res = $this->postJson('/api/v1/me/worker/redeem', ['code' => $plain]);

        $res->assertStatus(422)->assertJsonPath('code', 'worker_invite_exhausted');
    }

    public function test_redeem_ditolak_bila_tanggal_lewat(): void
    {
        $user = User::factory()->create();
        $this->auth($user);
        $plain = WorkerInviteCodeGenerator::generate();
        $this->makeCode($plain, ['expires_at' => now()->subDay()]);

        $res = $this->postJson('/api/v1/me/worker/redeem', ['code' => $plain]);

        $res->assertStatus(422)->assertJsonPath('code', 'worker_invite_expired');
    }

    public function test_redeem_ditolak_bila_kode_asing(): void
    {
        $user = User::factory()->create();
        $this->auth($user);

        $res = $this->postJson('/api/v1/me/worker/redeem', ['code' => 'Ab3!Xy9#']);

        $res->assertStatus(404)->assertJsonPath('code', 'worker_invite_invalid');
    }

    /**
     * Huruf kecil dan KAPITAL adalah simbol berbeda — salah kapitalisasi
     * berarti kode asing, bukan kode yang sama. Kalau hash dilowercase dulu,
     * separuh alfabetnya hilang dan syarat "kombinasi" jadi dusta.
     */
    public function test_redeem_case_sensitive_salah_kapital_ditolak(): void
    {
        $user = User::factory()->create();
        $this->auth($user);
        $plain = 'Ab3!Xy9#';
        $this->makeCode($plain);

        $this->postJson('/api/v1/me/worker/redeem', ['code' => mb_strtolower($plain)])
            ->assertStatus(404)->assertJsonPath('code', 'worker_invite_invalid');

        // Yang benar tetap lolos.
        $this->postJson('/api/v1/me/worker/redeem', ['code' => $plain])->assertOk();
    }

    public function test_generator_selalu_8char_empat_kelompok(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $code = WorkerInviteCodeGenerator::generate();
            $this->assertSame(8, mb_strlen($code));
            $this->assertTrue(WorkerInviteCodeGenerator::isWellFormed($code), "kode tak berbentuk: $code");
            $this->assertMatchesRegularExpression('/[a-z]/', $code);
            $this->assertMatchesRegularExpression('/[A-Z]/', $code);
            $this->assertMatchesRegularExpression('/[0-9]/', $code);
        }
    }
}
