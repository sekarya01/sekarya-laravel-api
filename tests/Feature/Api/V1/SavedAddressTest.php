<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET|PUT|DELETE /api/v1/me/address` — alamat tersimpan (B6).
 *
 * Alamat ini PRIBADI: ia dipakai mengisi lokasi tugas, tapi tidak pernah
 * keluar ke orang lain. Tabelnya dipisah dari domisili akun justru supaya
 * `PublicUserResource` tidak punya jalan untuk membocorkannya.
 */
final class SavedAddressTest extends TestCase
{
    use RefreshDatabase;

    private const ALAMAT = [
        'label' => 'Rumah',
        'address_line' => 'Jl. Dipatiukur No. 42, RT 03/RW 07, Coblong',
        'city' => 'Kota Bandung',
        'province' => 'Jawa Barat',
        'latitude' => -6.8915,
        'longitude' => 107.6167,
    ];

    public function test_it_starts_empty_without_creating_a_row(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)
            ->getJson(route('v1.me.address.show'))
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSame(0, UserAddress::query()->where('user_id', $user->getKey())->count());
    }

    public function test_the_first_put_creates_it_and_the_next_replaces_it_wholesale(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)
            ->putJson(route('v1.me.address.update'), self::ALAMAT)
            ->assertOk()
            ->assertJsonPath('data.label', 'Rumah')
            ->assertJsonPath('data.address_line', self::ALAMAT['address_line'])
            ->assertJsonPath('data.city', 'Kota Bandung')
            ->assertJsonPath('data.latitude', -6.8915)
            ->assertJsonPath('data.longitude', 107.6167);

        // PUT = ganti utuh: `label`/`province` yang tidak dikirim menjadi
        // kosong, bukan mempertahankan nilai lama.
        $this->asUser($user)
            ->putJson(route('v1.me.address.update'), [
                'address_line' => 'Jl. Baru No. 1',
                'city' => 'Kota Surabaya',
            ])
            ->assertOk()
            ->assertJsonPath('data.label', null)
            ->assertJsonPath('data.province', null)
            ->assertJsonPath('data.address_line', 'Jl. Baru No. 1')
            ->assertJsonPath('data.city', 'Kota Surabaya');

        $this->assertSame(1, UserAddress::query()->where('user_id', $user->getKey())->count());
    }

    public function test_coordinates_must_come_in_pairs(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)
            ->putJson(route('v1.me.address.update'), [
                'address_line' => 'Jl. X', 'city' => 'Bandung', 'latitude' => -6.9,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->asUser($user)
            ->putJson(route('v1.me.address.update'), [
                'address_line' => 'Jl. X', 'city' => 'Bandung', 'longitude' => 107.6,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');
    }

    public function test_delete_is_idempotent(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)->putJson(route('v1.me.address.update'), self::ALAMAT)->assertOk();

        $this->asUser($user)->deleteJson(route('v1.me.address.destroy'))->assertNoContent();
        $this->asUser($user)->getJson(route('v1.me.address.show'))->assertOk()->assertJsonPath('data', null);

        // Tidak ada = bukan galat.
        $this->asUser($user)->deleteJson(route('v1.me.address.destroy'))->assertNoContent();
    }

    public function test_one_users_address_is_never_anothers(): void
    {
        $mine = $this->activeUser();
        $theirs = $this->activeUser();

        $this->asUser($mine)->putJson(route('v1.me.address.update'), self::ALAMAT)->assertOk();

        $this->asUser($theirs)
            ->getJson(route('v1.me.address.show'))
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertSame(1, UserAddress::query()->where('user_id', $mine->getKey())->count());
        $this->assertSame(0, UserAddress::query()->where('user_id', $theirs->getKey())->count());
    }

    /**
     * Batas pengungkapan yang dijaga tabel terpisah: alamat tersimpan tidak
     * punya jalan keluar di profil publik siapa pun. Kalau ia bisa bocor,
     * memisahkannya dari domisili akun tidak membeli apa pun.
     */
    public function test_the_saved_address_never_leaks_in_the_public_profile(): void
    {
        $owner = $this->activeUser();
        $viewer = $this->activeUser();

        $this->asUser($owner)->putJson(route('v1.me.address.update'), self::ALAMAT)->assertOk();

        $response = $this->asUser($viewer)
            ->getJson(route('v1.users.show', $owner->ulid))
            ->assertOk();

        $this->assertStringNotContainsString(self::ALAMAT['address_line'], $response->getContent());
        $this->assertStringNotContainsString('Dipatiukur', $response->getContent());
        $response->assertJsonMissingPath('data.address_line');
        $response->assertJsonMissingPath('data.label');
    }

    public function test_the_guest_has_no_address(): void
    {
        $this->getJson(route('v1.me.address.show'))->assertUnauthorized();
        $this->putJson(route('v1.me.address.update'), self::ALAMAT)->assertUnauthorized();
        $this->deleteJson(route('v1.me.address.destroy'))->assertUnauthorized();
    }
}
