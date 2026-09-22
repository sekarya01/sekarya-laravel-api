<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\User;

use App\Enums\DevicePlatform;
use App\Models\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `POST /me/devices` dan `DELETE /me/devices/{token}` — pendaftaran perangkat
 * untuk push notification.
 */
final class DeviceTokenApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'fcm-token-abc:APA91bF-example-token-value';

    // ── akses ───────────────────────────────────────────────────────────────

    public function test_registering_a_device_requires_a_token(): void
    {
        $this->postJson(route('v1.me.devices.store'), [
            'token' => self::TOKEN,
            'platform' => DevicePlatform::Android->value,
        ])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    // ── validasi ────────────────────────────────────────────────────────────

    public function test_token_and_platform_are_required(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)->postJson(route('v1.me.devices.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token', 'platform']);
    }

    public function test_an_unknown_platform_is_rejected(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)->postJson(route('v1.me.devices.store'), [
            'token' => self::TOKEN,
            'platform' => 'windows',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }

    // ── pendaftaran ─────────────────────────────────────────────────────────

    public function test_a_new_device_is_registered_and_persisted(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)->postJson(route('v1.me.devices.store'), [
            'token' => self::TOKEN,
            'platform' => DevicePlatform::Android->value,
        ])
            ->assertCreated()
            ->assertJsonPath('data.platform', 'android')
            // Token perangkat TIDAK diulang di respons.
            ->assertJsonMissingPath('data.token');

        $this->assertDatabaseHas('device_tokens', [
            'user_id' => $user->getKey(),
            'token' => self::TOKEN,
            'platform' => DevicePlatform::Android->value,
        ]);
    }

    /**
     * Mengirim ulang token yang sama tidak menambah baris.
     *
     * Klien mendaftarkan ulang tiap aplikasi dibuka, jadi jalur ini adalah
     * jalur yang paling sering dilalui — bukan pengecualian.
     */
    public function test_registering_the_same_token_twice_updates_in_place(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)->postJson(route('v1.me.devices.store'), [
            'token' => self::TOKEN,
            'platform' => DevicePlatform::Android->value,
        ])->assertCreated();

        $this->asUser($user)->postJson(route('v1.me.devices.store'), [
            'token' => self::TOKEN,
            'platform' => DevicePlatform::Ios->value,
        ])->assertOk();

        $this->assertSame(1, DeviceToken::query()->where('token', self::TOKEN)->count());
        $this->assertDatabaseHas('device_tokens', [
            'token' => self::TOKEN,
            'platform' => DevicePlatform::Ios->value,
        ]);
    }

    /**
     * Satu ponsel, dua akun: token berpindah pemilik, tidak berlipat.
     *
     * Token FCM menempel pada pemasangan aplikasi. Kalau barisnya tidak
     * berpindah, notifikasi akun lama terus muncul di ponsel yang kini
     * dipegang orang lain.
     */
    public function test_a_device_that_switches_account_moves_to_the_new_owner(): void
    {
        $pertama = $this->activeUser();
        $kedua = $this->activeUser();

        $this->asUser($pertama)->postJson(route('v1.me.devices.store'), [
            'token' => self::TOKEN,
            'platform' => DevicePlatform::Android->value,
        ])->assertCreated();

        $this->asUser($kedua)->postJson(route('v1.me.devices.store'), [
            'token' => self::TOKEN,
            'platform' => DevicePlatform::Android->value,
        ])->assertOk();

        $this->assertSame(1, DeviceToken::query()->where('token', self::TOKEN)->count());
        $this->assertDatabaseHas('device_tokens', [
            'token' => self::TOKEN,
            'user_id' => $kedua->getKey(),
        ]);
    }

    // ── pelepasan ───────────────────────────────────────────────────────────

    public function test_a_user_can_forget_their_own_device(): void
    {
        $user = $this->activeUser();
        DeviceToken::factory()->create([
            'user_id' => $user->getKey(),
            'token' => self::TOKEN,
        ]);

        $this->asUser($user)
            ->deleteJson(route('v1.me.devices.destroy', ['token' => self::TOKEN]))
            ->assertNoContent();

        $this->assertDatabaseMissing('device_tokens', ['token' => self::TOKEN]);
    }

    /**
     * Logout akun lama TIDAK boleh mematikan perangkat yang kini milik akun
     * lain — pemeriksaan `user_id` di Action yang menjaganya.
     */
    public function test_a_user_cannot_forget_another_users_device(): void
    {
        $pemilik = $this->activeUser();
        $lain = $this->activeUser();

        DeviceToken::factory()->create([
            'user_id' => $pemilik->getKey(),
            'token' => self::TOKEN,
        ]);

        $this->asUser($lain)
            ->deleteJson(route('v1.me.devices.destroy', ['token' => self::TOKEN]))
            ->assertNoContent();

        $this->assertDatabaseHas('device_tokens', [
            'token' => self::TOKEN,
            'user_id' => $pemilik->getKey(),
        ]);
    }

    /** Token yang sudah tidak ada bukan galat — logout boleh idempoten. */
    public function test_forgetting_an_unknown_token_is_not_an_error(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)
            ->deleteJson(route('v1.me.devices.destroy', ['token' => 'tidak-pernah-ada']))
            ->assertNoContent();
    }
}
