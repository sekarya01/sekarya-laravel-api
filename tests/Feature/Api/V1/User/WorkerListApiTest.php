<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\User;

use App\Enums\Gender;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserWorker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `GET /api/v1/workers` — daftar pekerja siap kerja.
 */
final class WorkerListApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Pekerja dengan profil, dibuat pada waktu tertentu. */
    private function workerAt(string $at, array $user = [], array $profile = []): User
    {
        Carbon::setTestNow($at);

        $account = $this->activeUser($user);
        UserWorker::factory()->create(['user_id' => $account->getKey()] + $profile);

        Carbon::setTestNow();

        return $account->refresh();
    }

    /** @return list<string> ULID pada urutan yang dikembalikan API. */
    private function ids(array $json): array
    {
        return array_column($json['data'], 'id');
    }

    // ── akses ───────────────────────────────────────────────────────────────

    public function test_the_worker_list_requires_a_token(): void
    {
        $this->getJson(route('v1.workers.index'))
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    }

    // ── siapa yang muncul ───────────────────────────────────────────────────

    /**
     * Hanya yang benar-benar terdaftar sebagai pekerja.
     *
     * Ini sisi lain dari `ready_to_work`: kalau daftarnya memuat orang tanpa
     * profil pekerja, penanda itu berbohong di dua tempat sekaligus.
     */
    public function test_only_users_with_a_worker_profile_are_listed(): void
    {
        $worker = $this->workerAt('2026-09-01 10:00:00');
        $bukanPekerja = $this->activeUser();

        $ids = $this->ids(
            $this->asUser($worker)->getJson(route('v1.workers.index'))->assertOk()->json(),
        );

        $this->assertContains($worker->ulid, $ids);
        $this->assertNotContains($bukanPekerja->ulid, $ids);
    }

    /**
     * Akun yang ditangguhkan tetap punya baris profil.
     *
     * Daftar ini tempat pemberi kerja memilih orang untuk dihubungi, jadi
     * moderasi yang tidak terbaca di sini berarti moderasi yang tidak berlaku.
     */
    public function test_suspended_and_banned_workers_are_not_listed(): void
    {
        $aktif = $this->workerAt('2026-09-01 10:00:00');

        foreach ([UserStatus::Suspended, UserStatus::Banned] as $status) {
            $dimoderasi = $this->workerAt('2026-09-02 10:00:00');
            $dimoderasi->status = $status;
            $dimoderasi->save();

            $ids = $this->ids(
                $this->asUser($aktif)->getJson(route('v1.workers.index'))->assertOk()->json(),
            );

            $this->assertNotContains($dimoderasi->ulid, $ids, $status->value.' seharusnya tidak muncul');
        }
    }

    // ── urutan & pagination ─────────────────────────────────────────────────

    /**
     * Urutannya "yang baru SIAP BEKERJA", bukan "yang baru MENDAFTAR".
     *
     * Akun lama yang baru kemarin membuka profil pekerjanya harus muncul di
     * atas akun baru yang sudah jadi pekerja sejak lama.
     */
    public function test_the_newest_worker_profile_comes_first(): void
    {
        $akunLamaPekerjaBaru = $this->activeUser();
        $akunBaruPekerjaLama = $this->workerAt('2026-09-01 10:00:00');

        Carbon::setTestNow('2026-09-05 10:00:00');
        UserWorker::factory()->create(['user_id' => $akunLamaPekerjaBaru->getKey()]);
        Carbon::setTestNow();

        $ids = $this->ids(
            $this->asUser($akunBaruPekerjaLama)
                ->getJson(route('v1.workers.index'))->assertOk()->json(),
        );

        $this->assertSame(
            [$akunLamaPekerjaBaru->ulid, $akunBaruPekerjaLama->ulid],
            array_values(array_intersect($ids, [$akunLamaPekerjaBaru->ulid, $akunBaruPekerjaLama->ulid])),
        );
    }

    /** Cursor, bukan offset — `paginate()` dilarang di proyek ini. */
    public function test_the_page_meta_is_cursor_based(): void
    {
        $worker = $this->workerAt('2026-09-01 10:00:00');

        $meta = $this->asUser($worker)
            ->getJson(route('v1.workers.index', ['per_page' => 1]))
            ->assertOk()
            ->json('meta');

        $this->assertArrayHasKey('next_cursor', $meta);
        $this->assertArrayNotHasKey('current_page', $meta);
        $this->assertArrayNotHasKey('total', $meta);
    }

    /**
     * Cursor harus dihitung dari kolom `user_workers` yang benar-benar
     * diurutkan. Kalau item paginator ditukar jadi model lain, `next_cursor`
     * memakai `created_at` milik model itu — tidak ada galat, hanya halaman
     * kedua yang melompati orang.
     */
    public function test_paging_through_visits_every_worker_exactly_once(): void
    {
        $pemanggil = $this->workerAt('2026-08-01 10:00:00');

        $harapan = [$pemanggil->ulid];
        foreach (range(1, 4) as $i) {
            $harapan[] = $this->workerAt(sprintf('2026-09-%02d 10:00:00', $i))->ulid;
        }

        $terlihat = [];
        $url = route('v1.workers.index', ['per_page' => 2]);

        for ($halaman = 0; $halaman < 10 && $url !== null; $halaman++) {
            $body = $this->asUser($pemanggil)->getJson($url)->assertOk()->json();
            $terlihat = [...$terlihat, ...$this->ids($body)];

            $cursor = $body['meta']['next_cursor'] ?? null;
            $url = $cursor === null ? null : route('v1.workers.index', ['per_page' => 2, 'cursor' => $cursor]);
        }

        sort($harapan);
        $unik = array_values(array_unique($terlihat));
        sort($unik);

        $this->assertSame($harapan, $unik, 'ada pekerja yang terlewat atau terhitung dua kali');
        $this->assertCount(count($terlihat), array_unique($terlihat), 'ada baris yang muncul dua kali');
    }

    public function test_per_page_above_the_maximum_is_rejected(): void
    {
        $this->asUser($this->workerAt('2026-09-01 10:00:00'))
            ->getJson(route('v1.workers.index', ['per_page' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    // ── penyaring ───────────────────────────────────────────────────────────

    /**
     * INI jebakan yang paling mudah terjadi.
     *
     * Alamat kerja bawaannya DIWARISI dari akun, jadi menyaring
     * `user_workers.city` saja akan menghilangkan hampir setiap pekerja —
     * daftarnya tampak bekerja dan hampir selalu kosong.
     */
    public function test_the_city_filter_matches_workers_who_inherit_the_account_address(): void
    {
        $mewarisi = $this->workerAt('2026-09-01 10:00:00', ['city' => 'Bandung']);
        $kotaLain = $this->workerAt('2026-09-02 10:00:00', ['city' => 'Surabaya']);

        $ids = $this->ids(
            $this->asUser($mewarisi)
                ->getJson(route('v1.workers.index', ['city' => 'Bandung']))
                ->assertOk()->json(),
        );

        $this->assertContains($mewarisi->ulid, $ids);
        $this->assertNotContains($kotaLain->ulid, $ids);
    }

    /** Alamat kerja sendiri MENGGANTIKAN domisili akun, bukan menambahinya. */
    public function test_an_own_work_city_wins_over_the_account_city(): void
    {
        $pindahKerja = $this->workerAt(
            '2026-09-01 10:00:00',
            ['city' => 'Bandung'],
            ['city' => 'Surabaya'],
        );

        $this->assertContains($pindahKerja->ulid, $this->ids(
            $this->asUser($pindahKerja)
                ->getJson(route('v1.workers.index', ['city' => 'Surabaya']))->assertOk()->json(),
        ));

        // Dan TIDAK muncul di kota akunnya lagi — alamatnya sudah pindah.
        $this->assertNotContains($pindahKerja->ulid, $this->ids(
            $this->asUser($pindahKerja)
                ->getJson(route('v1.workers.index', ['city' => 'Bandung']))->assertOk()->json(),
        ));
    }

    public function test_workers_can_be_filtered_by_gender(): void
    {
        $perempuan = $this->workerAt('2026-09-01 10:00:00', ['gender' => Gender::Female]);
        $lakiLaki = $this->workerAt('2026-09-02 10:00:00', ['gender' => Gender::Male]);

        $ids = $this->ids(
            $this->asUser($lakiLaki)
                ->getJson(route('v1.workers.index', ['gender' => 'female']))->assertOk()->json(),
        );

        $this->assertContains($perempuan->ulid, $ids);
        $this->assertNotContains($lakiLaki->ulid, $ids);
    }

    public function test_an_unknown_gender_is_rejected(): void
    {
        $this->asUser($this->workerAt('2026-09-01 10:00:00'))
            ->getJson(route('v1.workers.index', ['gender' => 'perempuan']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gender');
    }

    // ── batas pengungkapan ──────────────────────────────────────────────────

    /**
     * Daftar ini mengembalikan paling banyak orang sekaligus, jadi kebocoran
     * di sini paling mahal. Bentuknya HARUS sama dengan PublicUserResource.
     */
    public function test_the_list_never_leaks_more_than_the_public_profile(): void
    {
        $worker = $this->workerAt(
            '2026-09-01 10:00:00',
            ['gender' => Gender::Male, 'birth_date' => '1995-03-02', 'address_line' => 'Jl. Kenanga 4'],
            ['latitude' => -6.2088, 'longitude' => 106.8456, 'radius_km' => 15, 'city' => 'Jakarta'],
        );

        $row = $this->asUser($worker)
            ->getJson(route('v1.workers.index'))->assertOk()->json('data.0');

        $this->assertSame('male', $row['gender']);
        $this->assertNotNull($row['age']);
        $this->assertTrue($row['ready_to_work']);
        $this->assertSame('Jakarta', $row['as_worker']['work_area']['city']);
        $this->assertSame(15, $row['as_worker']['work_area']['radius_km']);

        foreach (['birth_date', 'email', 'phone', 'address_line', 'latitude', 'longitude'] as $rahasia) {
            $this->assertArrayNotHasKey($rahasia, $row, "{$rahasia} tidak boleh keluar di daftar pekerja");
        }

        $this->assertArrayNotHasKey('latitude', $row['as_worker']['work_area']);
    }

    /** `ready_to_work` diturunkan dari ada-tidaknya profil, bukan kolom. */
    public function test_ready_to_work_follows_the_worker_profile(): void
    {
        $user = $this->activeUser();

        $this->asUser($user)->getJson(route('v1.me.show'))
            ->assertOk()
            ->assertJsonPath('data.ready_to_work', false);

        $this->asUser($user)->putJson(route('v1.me.worker.update'), ['radius_km' => 10])
            ->assertCreated();

        $this->asUser($user)->getJson(route('v1.me.show'))
            ->assertOk()
            ->assertJsonPath('data.ready_to_work', true);
    }

    /**
     * Relasi ke pemiliknya harus bisa dimuat sendiri — profil yang diambil
     * lepas dari `User` tidak punya relasi yang sudah dipasang.
     */
    public function test_a_worker_profile_can_load_its_owner_on_its_own(): void
    {
        $user = $this->workerAt('2026-09-01 10:00:00', ['name' => 'Budi Santoso']);

        $profil = UserWorker::query()->where('user_id', $user->getKey())->sole();

        $this->assertSame('Budi Santoso', $profil->user->name);
        $this->assertSame('Budi Santoso', $profil->resolvedName());
    }
}
