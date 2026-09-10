<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\User;

use App\Enums\Gender;
use App\Models\User;
use App\Models\UserWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `GET|PUT /api/v1/me/worker` — profil pekerja, tabel sendiri.
 */
final class WorkerProfileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function worker(array $attributes = []): User
    {
        return $this->activeUser($attributes + [
            'name' => 'Budi Santoso',
            'gender' => Gender::Male,
            'birth_date' => '1995-03-02',
            'address_line' => 'Jl. Kenanga 4',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40123',
        ]);
    }

    // ── akses ───────────────────────────────────────────────────────────────

    public function test_the_worker_profile_requires_a_token(): void
    {
        $this->getJson(route('v1.me.worker.show'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');

        $this->putJson(route('v1.me.worker.update'), [])
            ->assertUnauthorized();
    }

    /** Token long_lived hanya boleh menukar diri jadi access token. */
    public function test_a_long_lived_token_cannot_read_the_worker_profile(): void
    {
        $this->asUserWithLongLived($this->worker())
            ->getJson(route('v1.me.worker.show'))
            ->assertForbidden();
    }

    // ── baca ────────────────────────────────────────────────────────────────

    /**
     * Membaca profil sendiri TIDAK boleh menulis baris.
     *
     * Kalau GET membuat baris, sekadar membuka halaman profil sudah menambah
     * data — dan `configured` tidak akan pernah bisa menjawab "sudah pernah
     * diisi atau belum", karena jawabannya selalu ya sesudah pembacaan
     * pertama.
     */
    public function test_reading_an_empty_profile_creates_nothing_and_inherits_everything(): void
    {
        $user = $this->worker(['phone' => '+628111000111']);

        $this->asUser($user)
            ->getJson(route('v1.me.worker.show'))
            ->assertOk()
            ->assertJsonPath('data.configured', false)
            ->assertJsonPath('data.name', 'Budi Santoso')
            ->assertJsonPath('data.contact_phone', '+628111000111')
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.address.city', 'Bandung')
            ->assertJsonPath('data.as_worker.tasks_completed', 0)
            // Yang diwarisi terbaca null di blok `own` — itulah gunanya.
            ->assertJsonPath('data.own.display_name', null)
            ->assertJsonPath('data.own.contact_phone', null);

        $this->assertSame(0, UserWorker::query()->where('user_id', $user->getKey())->count());
    }

    public function test_the_age_is_derived_not_stored(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');

        $this->asUser($this->worker(['birth_date' => '2000-09-11']))
            ->getJson(route('v1.me.worker.show'))
            ->assertOk()
            ->assertJsonPath('data.age', 25);
    }

    // ── tulis ───────────────────────────────────────────────────────────────

    /**
     * PUT pertama MEMBUAT profilnya (201), yang berikutnya hanya mengubah
     * (200) — dan hasil akhirnya sama berapa kali pun dikirim. Itu yang
     * membuat klien tidak perlu tahu lebih dulu apakah profilnya sudah ada.
     */
    public function test_putting_a_profile_creates_it_once_and_is_idempotent(): void
    {
        $user = $this->worker(['phone' => '+628111000111']);

        $this->asUser($user)
            ->putJson(route('v1.me.worker.update'), ['display_name' => 'Budi Tukang AC'])
            ->assertCreated()
            ->assertJsonPath('data.configured', true)
            ->assertJsonPath('data.name', 'Budi Tukang AC')
            ->assertJsonPath('data.own.display_name', 'Budi Tukang AC')
            // Yang tidak disebut tetap ikut akun.
            ->assertJsonPath('data.contact_phone', '+628111000111');

        $this->asUser($user)
            ->putJson(route('v1.me.worker.update'), ['display_name' => 'Budi Tukang AC'])
            ->assertOk();

        $this->assertSame(1, UserWorker::query()->where('user_id', $user->getKey())->count());
    }

    /**
     * Null di sini berarti "kembali ikut akun", dan itu HARUS benar-benar
     * terjadi. Validasi menandai field-nya `nullable`, jadi permintaan ini
     * diterima; permintaan yang diterima tapi diam-diam tidak dikerjakan
     * adalah bug yang tidak terlihat dari sisi klien.
     */
    public function test_sending_null_returns_a_field_to_inheriting_from_the_account(): void
    {
        $user = $this->worker();

        $this->asUser($user)
            ->putJson(route('v1.me.worker.update'), ['display_name' => 'Budi Tukang AC'])
            ->assertCreated()
            ->assertJsonPath('data.own.display_name', 'Budi Tukang AC');

        $this->asUser($user)
            ->putJson(route('v1.me.worker.update'), ['display_name' => null])
            ->assertOk()
            ->assertJsonPath('data.own.display_name', null)
            ->assertJsonPath('data.name', 'Budi Santoso');
    }

    /** Field yang tidak disebut tidak boleh ikut terhapus. */
    public function test_omitting_a_field_leaves_it_untouched(): void
    {
        $user = $this->worker();

        $this->asUser($user)->putJson(route('v1.me.worker.update'), [
            'display_name' => 'Budi Tukang AC',
            'contact_phone' => '+628222000222',
        ])->assertCreated();

        $this->asUser($user)
            ->putJson(route('v1.me.worker.update'), ['city' => 'Surabaya'])
            ->assertOk()
            ->assertJsonPath('data.own.display_name', 'Budi Tukang AC')
            ->assertJsonPath('data.own.contact_phone', '+628222000222');
    }

    public function test_an_own_address_replaces_the_account_address_wholesale(): void
    {
        $this->asUser($this->worker())
            ->putJson(route('v1.me.worker.update'), ['city' => 'Surabaya'])
            ->assertCreated()
            ->assertJsonPath('data.address.city', 'Surabaya')
            // Bukan gabungan dua alamat: jalannya TIDAK ikut dari akun.
            ->assertJsonPath('data.address.address_line', null)
            ->assertJsonPath('data.address.province', null);
    }

    public function test_the_work_location_is_stored(): void
    {
        $this->asUser($this->worker())
            ->putJson(route('v1.me.worker.update'), [
                'latitude' => -6.2088,
                'longitude' => 106.8456,
                'radius_km' => 15,
            ])
            ->assertCreated()
            ->assertJsonPath('data.work_location.latitude', -6.2088)
            ->assertJsonPath('data.work_location.longitude', 106.8456)
            ->assertJsonPath('data.work_location.radius_km', 15);
    }

    // ── validasi ────────────────────────────────────────────────────────────

    /** Satu lintang tanpa bujur bukan lokasi yang kurang lengkap — ia bukan lokasi. */
    public function test_coordinates_must_come_in_pairs(): void
    {
        $user = $this->worker();

        $this->asUser($user)
            ->putJson(route('v1.me.worker.update'), ['latitude' => -6.2088])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('longitude');

        $this->asUser($user)
            ->putJson(route('v1.me.worker.update'), ['longitude' => 106.8456])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('latitude');
    }

    public function test_the_coordinates_are_bounded(): void
    {
        $this->asUser($this->worker())
            ->putJson(route('v1.me.worker.update'), ['latitude' => 91, 'longitude' => 181])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_the_contact_phone_must_look_like_a_phone_number(): void
    {
        $this->asUser($this->worker())
            ->putJson(route('v1.me.worker.update'), ['contact_phone' => 'hubungi saya'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_phone');
    }

    public function test_the_radius_is_bounded(): void
    {
        $this->asUser($this->worker())
            ->putJson(route('v1.me.worker.update'), ['radius_km' => 5000])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('radius_km');
    }

    // ── penjaga ─────────────────────────────────────────────────────────────

    /**
     * Reputasi tidak boleh datang dari payload — kalau bisa, ia bukan
     * reputasi melainkan kolom isian.
     */
    public function test_reputation_cannot_be_set_through_the_endpoint(): void
    {
        $user = $this->worker();

        $this->asUser($user)
            ->putJson(route('v1.me.worker.update'), [
                'worker_rating_avg' => 5,
                'worker_rating_count' => 999,
                'tasks_completed' => 999,
                'bids_won' => 999,
            ])
            ->assertCreated()
            ->assertJsonPath('data.as_worker.rating_avg', 0)
            ->assertJsonPath('data.as_worker.rating_count', 0)
            ->assertJsonPath('data.as_worker.tasks_completed', 0)
            ->assertJsonPath('data.as_worker.bids_won', 0);
    }

    /**
     * Identitas hanya punya satu sumber. Menerimanya di sini berarti satu
     * orang bisa punya dua tanggal lahir yang sama-sama "benar".
     */
    public function test_identity_cannot_be_changed_through_the_worker_profile(): void
    {
        $user = $this->worker();

        $this->asUser($user)
            ->putJson(route('v1.me.worker.update'), [
                'gender' => 'female',
                'birth_date' => '1970-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('data.gender', 'male');

        $this->assertSame(Gender::Male, $user->refresh()->gender);
        $this->assertSame('1995-03-02', $user->birth_date->toDateString());
    }

    /** Profil orang lain tidak bisa disentuh: endpoint-nya hanya mengenal "saya". */
    public function test_one_user_never_touches_another_users_profile(): void
    {
        $mine = $this->worker();
        $theirs = $this->activeUser();

        $this->asUser($mine)
            ->putJson(route('v1.me.worker.update'), ['display_name' => 'Punya saya'])
            ->assertCreated();

        $this->assertSame(0, UserWorker::query()->where('user_id', $theirs->getKey())->count());
    }
}
