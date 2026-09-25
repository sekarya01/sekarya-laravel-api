<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** `GET users/{user}` — profil publik satu orang (B4). */
final class PublicUserProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    private function subject(): User
    {
        $user = User::factory()->withSkills(['cuci-ac'])->create([
            'email' => 'rahasia.orang@example.test',
            'phone' => '+6281299998888',
            'address_line' => 'Jl. Kenanga No. 12 RT 01',
            'bio' => 'Teknisi AC 5 tahun',
        ]);
        UserWorker::factory()->create([
            'user_id' => $user->getKey(),
            'headline' => 'Teknisi AC',
            'address_line' => 'Jl. Melati No. 3',
            'city' => 'Kota Bandung',
            'latitude' => -6.9123456,
            'longitude' => 107.6054321,
            'radius_km' => 10,
            'contact_phone' => '+6281377776666',
        ]);
        $this->verifyIdentity($user);

        return $user->refresh();
    }

    public function test_an_active_account_is_shown_with_skills_and_nothing_private(): void
    {
        $user = $this->subject();

        $response = $this->asUser($this->activeUser())
            ->getJson(route('v1.users.show', $user->ulid))
            ->assertOk()
            ->assertJsonPath('data.id', $user->ulid)
            ->assertJsonPath('data.bio', 'Teknisi AC 5 tahun')
            ->assertJsonPath('data.identity_verified', true)
            ->assertJsonPath('data.ready_to_work', true)
            ->assertJsonPath('data.as_worker.headline', 'Teknisi AC')
            ->assertJsonPath('data.as_worker.work_area.city', 'Kota Bandung')
            ->assertJsonPath('data.as_worker.work_area.radius_km', 10)
            ->assertJsonPath('data.skills.0.slug', 'cuci-ac');

        $body = (string) $response->getContent();
        foreach ([
            'rahasia.orang@example.test', '+6281299998888', '81299998888', '+6281377776666',
            'Jl. Kenanga', 'Jl. Melati', '-6.912', '107.605', 'birth_date', 'email', 'phone',
            'address_line', 'latitude', 'balance',
        ] as $secret) {
            $this->assertStringNotContainsString($secret, $body, $secret.' leaked');
        }
    }

    public function test_non_active_accounts_are_indistinguishable_from_missing_ones(): void
    {
        $viewer = $this->activeUser();
        $missing = $this->asUser($viewer)->getJson(route('v1.users.show', (string) Str::ulid()))
            ->assertNotFound();

        $user = $this->subject();

        foreach ([UserStatus::PendingVerification, UserStatus::Suspended, UserStatus::Banned] as $status) {
            $user->forceFill(['status' => $status])->save();

            $response = $this->asUser($viewer)->getJson(route('v1.users.show', $user->ulid))
                ->assertNotFound();

            $this->assertSame($missing->json('message'), $response->json('message'), $status->value.' differs from missing');
            $this->assertSame($missing->json('code'), $response->json('code'));
            $this->assertStringNotContainsString($user->ulid, (string) $response->getContent());
        }
    }

    public function test_soft_deleted_accounts_are_404(): void
    {
        $user = $this->subject();
        $user->delete();

        $this->asUser($this->activeUser())->getJson(route('v1.users.show', $user->ulid))->assertNotFound();
    }

    public function test_it_requires_an_access_token(): void
    {
        $user = $this->subject();

        $this->getJson(route('v1.users.show', $user->ulid))->assertUnauthorized();
        $this->asUserWithLongLived($this->activeUser())
            ->getJson(route('v1.users.show', $user->ulid))->assertForbidden();
    }

    public function test_it_does_not_run_a_query_per_badge(): void
    {
        $user = $this->subject();
        $viewer = $this->activeUser();

        DB::enableQueryLog();
        $this->asUser($viewer)->getJson(route('v1.users.show', $user->ulid))->assertOk();
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();

        $this->assertSame(0, $queries->filter(
            fn (string $q): bool => str_starts_with($q, 'select exists') && str_contains($q, 'user_worker_verifications'),
        )->count(), 'identity badge fell back to a per-call exists query');
    }
}
