<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CheckAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_when_fresh(): void
    {
        $this->postJson(route('v1.auth.check-availability'), [
            'email' => 'baru@sekarya.test',
            'username' => 'baru.xyz',
            'phone' => '+628990011223',
        ])
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonStructure(['message', 'data' => ['available']]);
    }

    public function test_rejects_taken_email_username_phone(): void
    {
        $this->activeUser([
            'email' => 'pakai@sekarya.test',
            'username' => 'pakai.xyz',
            'phone' => '+628111222333',
        ]);

        $this->postJson(route('v1.auth.check-availability'), [
            'email' => 'pakai@sekarya.test',
            'username' => 'pakai.xyz',
            'phone' => '+628111222333',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'username', 'phone']);
    }

    public function test_optional_fields_only_checked_when_present(): void
    {
        $this->activeUser(['email' => 'pakai@sekarya.test']);

        // username/phone kosong → hanya email yang dinilai.
        $this->postJson(route('v1.auth.check-availability'), ['email' => 'bebas@sekarya.test'])
            ->assertOk();
    }

    public function test_requires_at_least_one_field(): void
    {
        $this->postJson(route('v1.auth.check-availability'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'username', 'phone']);
    }

    public function test_rejects_bad_format(): void
    {
        $this->postJson(route('v1.auth.check-availability'), ['email' => 'bukan-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }
}
