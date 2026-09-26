<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Ganti kata sandi saat sudah login (G4).
 *
 * Sandi lama WAJIB benar, dan seluruh token dicabut setelah sandi berganti.
 */
final class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    private function userWithPassword(string $password = 'password'): User
    {
        return $this->activeUser(['password' => Hash::make($password)]);
    }

    public function test_a_user_can_change_their_password(): void
    {
        $user = $this->userWithPassword();
        $user->createToken('sesi-lain');

        $this->asUser($user)
            ->postJson(route('v1.auth.change-password'), [
                'current_password' => 'password',
                'password' => 'sandiBaru123',
                'password_confirmation' => 'sandiBaru123',
            ])
            ->assertNoContent();

        $user->refresh();

        $this->assertTrue(Hash::check('sandiBaru123', $user->password));
        // Token yang terbit sebelum pergantian tidak bertahan.
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_the_current_password_must_be_correct(): void
    {
        $user = $this->userWithPassword();

        $this->asUser($user)
            ->postJson(route('v1.auth.change-password'), [
                'current_password' => 'salah-sekali',
                'password' => 'sandiBaru123',
                'password_confirmation' => 'sandiBaru123',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_the_new_password_must_be_confirmed_and_strong(): void
    {
        $user = $this->userWithPassword();

        $this->asUser($user)
            ->postJson(route('v1.auth.change-password'), [
                'current_password' => 'password',
                'password' => 'pendek',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_a_guest_cannot_change_a_password(): void
    {
        $this->postJson(route('v1.auth.change-password'), [
            'current_password' => 'password',
            'password' => 'sandiBaru123',
            'password_confirmation' => 'sandiBaru123',
        ])->assertUnauthorized();
    }
}
