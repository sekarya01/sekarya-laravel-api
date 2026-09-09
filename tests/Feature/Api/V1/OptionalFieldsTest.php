<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Field opsional yang tidak dipakai alur utama, tapi ada di kontrak. */
final class OptionalFieldsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    public function test_creating_a_task_with_every_optional_field(): void
    {
        $poster = $this->activeUser();

        $this->asUser($poster)
            ->postJson(route('v1.tasks.store'), [
                'category_id' => $this->anyCategory()->getKey(),
                'title' => 'Bersihkan rumah 2 lantai',
                'description' => 'Sapu, pel, kamar mandi.',
                'budget_min' => 200_000,
                'budget_max' => 400_000,
                'options' => [['label' => 'Bawa alat sendiri', 'value' => true]],
                'photos' => ['before/a.jpg'],
                'skills' => ['bersih-umum'],
                'location_text' => 'Jl. Melati No. 12, patokan warung biru',
                'city' => 'Jakarta',
                'latitude' => -6.1754,
                'longitude' => 106.8272,
                'is_remote' => false,
                'needed_at' => now()->addDays(3)->toIso8601String(),
                'bidding_closes_at' => now()->addDay()->toIso8601String(),
                'publish_now' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.budget.max', 400_000)
            ->assertJsonPath('data.location.text', 'Jl. Melati No. 12, patokan warung biru')
            ->assertJsonPath('data.location.city', 'Jakarta')
            ->assertJsonPath('data.location.is_remote', false)
            ->assertJsonPath('data.options.0.label', 'Bawa alat sendiri')
            ->assertJsonCount(1, 'data.photos')
            ->assertJsonCount(1, 'data.skills');
    }

    public function test_a_remote_task_needs_no_coordinates(): void
    {
        $this->asUser($this->activeUser())
            ->postJson(route('v1.tasks.store'), [
                'category_id' => $this->anyCategory()->getKey(),
                'title' => 'Input data',
                'description' => 'Bisa dikerjakan dari mana saja.',
                'budget_min' => 100_000,
                'is_remote' => true,
                'publish_now' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.location.is_remote', true)
            ->assertJsonPath('data.location.latitude', null);
    }

    public function test_placing_a_bid_with_every_optional_field(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();

        $task = $this->asUser($poster)->postJson(route('v1.tasks.store'), [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Cuci AC',
            'description' => 'Servis.',
            'budget_min' => 150_000,
            'publish_now' => true,
        ])->json('data.id');

        $this->asUser($worker)
            ->postJson(route('v1.tasks.bids.store', $task), [
                'amount' => 220_000,
                'message' => 'Bawa alat sendiri',
                'option_responses' => [['label' => 'Bawa alat sendiri', 'value' => true]],
                'estimated_hours' => 3.5,
                'can_start_at' => now()->addHours(2)->toIso8601String(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.estimated_hours', 3.5)
            ->assertJsonCount(1, 'data.option_responses')
            ->assertJsonPath('data.message', 'Bawa alat sendiri');

        $this->assertNotNull(
            $this->asUser($worker)->getJson(route('v1.bids.mine'))->json('data.0.can_start_at'),
        );
    }

    public function test_a_bid_rejects_a_past_start_time(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = $this->asUser($poster)->postJson(route('v1.tasks.store'), [
            'category_id' => $this->anyCategory()->getKey(),
            'title' => 'Cuci AC',
            'description' => 'Servis.',
            'budget_min' => 150_000,
            'publish_now' => true,
        ])->json('data.id');

        $this->asUser($worker)
            ->postJson(route('v1.tasks.bids.store', $task), [
                'amount' => 220_000,
                'can_start_at' => now()->subDay()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['can_start_at']);
    }

    public function test_updating_every_profile_field(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)
            ->patchJson(route('v1.me.update'), [
                'name' => 'Budi Prasetyo',
                'bio' => 'Tukang berpengalaman.',
                'skills' => ['cuci-ac'],
                'avatar_path' => 'avatars/budi.jpg',
                'address_line' => 'Jl. Melati No. 12',
                'city' => 'Jakarta',
                'province' => 'DKI Jakarta',
                'postal_code' => '10110',
                'active_mode' => 'working',
                'theme' => 'dark',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Budi Prasetyo')
            ->assertJsonPath('data.bio', 'Tukang berpengalaman.')
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.active_mode', 'working')
            ->assertJsonPath('data.domicile.address_line', 'Jl. Melati No. 12')
            ->assertJsonPath('data.domicile.postal_code', '10110')
            ->assertJsonPath('data.avatar_url', fn (?string $url): bool => $url !== null
                && str_contains($url, 'avatars/budi.jpg'));
    }
}
