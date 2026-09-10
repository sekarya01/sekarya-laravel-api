<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\User;

use App\Enums\Gender;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Jenis kelamin dan tanggal lahir di `users`, dan umur yang diturunkan darinya.
 */
final class IdentityFieldsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Tanggal lahir sah yang berumur tepat di atas batas minimum. */
    private function validBirthDate(): string
    {
        return now()->subYears(30)->toDateString();
    }

    // ── sunting profil ──────────────────────────────────────────────────────

    public function test_gender_and_birth_date_can_be_set_and_the_age_follows(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');

        $this->asUser($this->activeUser())
            ->patchJson(route('v1.me.update'), [
                'gender' => 'female',
                'birth_date' => '1995-03-02',
            ])
            ->assertOk()
            ->assertJsonPath('data.gender', 'female')
            ->assertJsonPath('data.birth_date', '1995-03-02')
            ->assertJsonPath('data.age', 31);
    }

    /**
     * Umur TIDAK diterima dari klien.
     *
     * Angka yang dikirim klien sudah salah pada hari ulang tahun pengirimnya,
     * dan tidak ada cara memperbaikinya di sisi server. Yang disimpan
     * tanggalnya; umur dihitung setiap kali dibaca.
     */
    public function test_a_submitted_age_is_ignored(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');

        $this->asUser($this->activeUser())
            ->patchJson(route('v1.me.update'), [
                'birth_date' => '1995-03-02',
                'age' => 99,
            ])
            ->assertOk()
            ->assertJsonPath('data.age', 31);
    }

    public function test_gender_only_accepts_the_two_known_values(): void
    {
        $this->asUser($this->activeUser())
            ->patchJson(route('v1.me.update'), ['gender' => 'laki-laki'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('gender');
    }

    /** Batas umur minimum: usia KTP, dan verifikasi identitas bertumpu padanya. */
    public function test_someone_below_the_minimum_age_is_rejected(): void
    {
        $tooYoung = now()->subYears((int) config('sekarya.profile.min_age'))
            ->addDay()->toDateString();

        $this->asUser($this->activeUser())
            ->patchJson(route('v1.me.update'), ['birth_date' => $tooYoung])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('birth_date');
    }

    public function test_exactly_the_minimum_age_is_accepted(): void
    {
        $exactly = now()->subYears((int) config('sekarya.profile.min_age'))->toDateString();

        $this->asUser($this->activeUser())
            ->patchJson(route('v1.me.update'), ['birth_date' => $exactly])
            ->assertOk()
            ->assertJsonPath('data.age', (int) config('sekarya.profile.min_age'));
    }

    public function test_an_absurd_birth_date_is_rejected(): void
    {
        $this->asUser($this->activeUser())
            ->patchJson(route('v1.me.update'), ['birth_date' => '1025-01-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('birth_date');
    }

    /**
     * Format tanggalnya persis satu.
     *
     * Tanpa `date_format`, Laravel menerima apa pun yang bisa diurai
     * strtotime — termasuk "01/02/2003", yang artinya berbeda di dua benua.
     */
    public function test_the_birth_date_format_is_fixed(): void
    {
        foreach (['02-03-1995', '01/02/2003', 'next tuesday'] as $bad) {
            $this->asUser($this->activeUser())
                ->patchJson(route('v1.me.update'), ['birth_date' => $bad])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('birth_date');
        }
    }

    /**
     * `nullable` di aturan validasi adalah JANJI bahwa null bisa dikirim.
     *
     * Permintaan yang dijawab 200 tapi diam-diam tidak mengubah apa pun lebih
     * buruk daripada permintaan yang ditolak: klien tidak punya cara tahu.
     */
    public function test_sending_null_clears_the_field(): void
    {
        $user = $this->activeUser([
            'gender' => Gender::Male,
            'birth_date' => $this->validBirthDate(),
        ]);

        $this->asUser($user)
            ->patchJson(route('v1.me.update'), ['gender' => null, 'birth_date' => null])
            ->assertOk()
            ->assertJsonPath('data.gender', null)
            ->assertJsonPath('data.birth_date', null)
            ->assertJsonPath('data.age', null);

        $this->assertNull($user->refresh()->gender);
        $this->assertNull($user->birth_date);
    }

    /** Yang tidak disebut tidak boleh ikut terhapus. */
    public function test_omitting_a_field_leaves_it_untouched(): void
    {
        $user = $this->activeUser([
            'gender' => Gender::Male,
            'birth_date' => $this->validBirthDate(),
        ]);

        $this->asUser($user)
            ->patchJson(route('v1.me.update'), ['bio' => 'Tukang AC 10 tahun'])
            ->assertOk()
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.birth_date', $this->validBirthDate());
    }

    // ── batas pengungkapan ──────────────────────────────────────────────────

    /**
     * Orang lain melihat UMUR, tidak pernah TANGGAL LAHIR.
     *
     * Umur adalah bahan pertimbangan yang wajar; tanggal lahir persis adalah
     * bahan pembobolan identitas — bank dan layanan publik memakainya sebagai
     * verifikasi.
     */
    public function test_others_see_the_age_but_never_the_birth_date(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');

        $poster = $this->activeUser();
        $worker = $this->activeUser([
            'gender' => Gender::Female,
            'birth_date' => '1995-03-02',
        ]);

        $task = Task::factory()->create([
            'poster_id' => $poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
        ]);
        Bid::factory()->create([
            'task_id' => $task->getKey(),
            'bidder_id' => $worker->getKey(),
        ]);

        $response = $this->asUser($poster)
            ->getJson(route('v1.tasks.bids.index', $task))
            ->assertOk()
            ->assertJsonPath('data.0.bidder.gender', 'female')
            ->assertJsonPath('data.0.bidder.age', 31);

        $this->assertArrayNotHasKey('birth_date', $response->json('data.0.bidder'));
    }

    /** Pengelola melihat tanggalnya: verifikasi identitas mencocokkannya dengan KTP. */
    public function test_an_admin_sees_the_full_birth_date(): void
    {
        $user = $this->activeUser([
            'gender' => Gender::Male,
            'birth_date' => '1995-03-02',
        ]);

        $this->asAdmin($this->activeAdmin())
            ->getJson(route('v1.admin.users.show', $user))
            ->assertOk()
            ->assertJsonPath('data.gender', 'male')
            ->assertJsonPath('data.birth_date', '1995-03-02')
            ->assertJsonPath('data.age', $user->age);
    }

    /** Pendaftaran sengaja TIDAK berubah — klien lama tidak boleh putus. */
    public function test_registration_still_works_without_the_new_fields(): void
    {
        $this->postJson(route('v1.auth.register'), [
            'name' => 'Pendaftar Baru',
            'email' => 'pendaftar.baru@sekarya.test',
            'phone' => '+628111222333',
            'password' => 'RahasiaKuat2026',
            'password_confirmation' => 'RahasiaKuat2026',
        ])->assertAccepted();

        $user = User::query()->where('email', 'pendaftar.baru@sekarya.test')->sole();

        $this->assertNull($user->gender);
        $this->assertNull($user->birth_date);
        $this->assertNull($user->age);
    }
}
