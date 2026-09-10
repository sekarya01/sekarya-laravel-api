<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\Gender;
use App\Models\User;
use App\Models\UserWorker;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Umur turunan, dan aturan "NULL berarti pakai punya akun".
 *
 * Keduanya diuji lewat JALUR NYATA (`create()`, bukan factory): factory
 * membuat modelnya di dalam `Model::unguarded()`, jadi kolom yang sengaja
 * tidak mass-assignable akan lolos di sana dan bug "nilai berhak hilang tanpa
 * galat" tidak akan pernah muncul.
 */
final class UserWorkerModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── umur ────────────────────────────────────────────────────────────────

    public function test_age_is_derived_from_the_birth_date(): void
    {
        Carbon::setTestNow('2026-09-10 12:00:00');

        $user = User::factory()->create(['birth_date' => '2000-01-15']);

        $this->assertSame(26, $user->age);
    }

    public function test_age_is_null_without_a_birth_date(): void
    {
        $this->assertNull(User::factory()->create(['birth_date' => null])->age);
    }

    /**
     * Umur dipotong ke bawah, tidak dibulatkan.
     *
     * Orang yang ulang tahunnya besok berumur 25, bukan 26 — dan kalau
     * dibulatkan, batas umur minimum bisa dilewati sehari lebih awal.
     */
    public function test_age_does_not_round_up_the_day_before_a_birthday(): void
    {
        Carbon::setTestNow('2026-09-10 23:59:59');

        $user = User::factory()->create(['birth_date' => '2000-09-11']);

        $this->assertSame(25, $user->age);
    }

    public function test_age_increments_on_the_birthday_itself(): void
    {
        $user = User::factory()->create(['birth_date' => '2000-09-11']);

        Carbon::setTestNow('2026-09-11 00:00:01');

        $this->assertSame(26, $user->refresh()->age);
    }

    /**
     * INI alasan umur tidak disimpan sebagai kolom.
     *
     * Barisnya tidak disentuh sama sekali di antara dua pembacaan — yang
     * berubah cuma harinya. Kolom `age` akan tetap menjawab 25 sampai ada yang
     * menulis ulang barisnya, dan tidak ada permintaan HTTP yang datang karena
     * seseorang bertambah tua.
     */
    public function test_age_changes_without_the_row_ever_being_written(): void
    {
        Carbon::setTestNow('2026-09-10 08:00:00');

        $user = User::factory()->create(['birth_date' => '2000-09-11']);
        $updatedAt = $user->updated_at;

        $this->assertSame(25, $user->age);

        Carbon::setTestNow('2026-09-11 08:00:00');

        $fresh = User::query()->whereKey($user->getKey())->sole();

        $this->assertSame(26, $fresh->age);
        $this->assertEquals($updatedAt, $fresh->updated_at);
    }

    public function test_gender_is_cast_to_the_enum(): void
    {
        $user = User::factory()->create(['gender' => Gender::Female]);

        $this->assertSame(Gender::Female, $user->refresh()->gender);
        $this->assertSame('Perempuan', $user->gender->label());
    }

    // ── resolusi "NULL = pakai punya akun" ──────────────────────────────────

    public function test_an_empty_worker_profile_inherits_everything_from_the_account(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'phone' => '+628111000111',
            'avatar_path' => 'avatars/budi.jpg',
            'address_line' => 'Jl. Kenanga 4',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40123',
            'gender' => Gender::Male,
            'birth_date' => '1995-03-02',
        ]);

        $profile = $user->workerProfile()->create([]);
        $profile->setRelation('user', $user);

        $this->assertSame('Budi Santoso', $profile->resolvedName());
        $this->assertSame('+628111000111', $profile->resolvedPhone());
        $this->assertSame('avatars/budi.jpg', $profile->resolvedAvatarPath());
        $this->assertSame(Gender::Male, $profile->gender());
        $this->assertSame($user->age, $profile->age());
        $this->assertSame([
            'address_line' => 'Jl. Kenanga 4',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40123',
        ], $profile->resolvedAddress());
    }

    public function test_its_own_values_win_over_the_account(): void
    {
        $user = User::factory()->create(['name' => 'Budi Santoso', 'phone' => '+628111000111']);

        $profile = $user->workerProfile()->create([
            'display_name' => 'Budi Tukang AC',
            'contact_phone' => '+628222000222',
        ]);
        $profile->setRelation('user', $user);

        $this->assertSame('Budi Tukang AC', $profile->resolvedName());
        $this->assertSame('+628222000222', $profile->resolvedPhone());
    }

    /**
     * Alamat diresolusi sebagai SATU KESATUAN.
     *
     * Kalau tiap kolom jatuh sendiri-sendiri, pekerja yang menuliskan alamat
     * kerjanya di kota lain akan mendapat gabungan dua alamat: jalannya dari
     * profil pekerja, kotanya dari domisili akun. Itu alamat yang tidak pernah
     * ada, dan pemberi kerja akan mendatanginya.
     */
    public function test_a_partial_own_address_never_mixes_with_the_account_address(): void
    {
        $user = User::factory()->create([
            'address_line' => 'Jl. Kenanga 4',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
            'postal_code' => '40123',
        ]);

        $profile = $user->workerProfile()->create(['city' => 'Surabaya']);
        $profile->setRelation('user', $user);

        $this->assertSame([
            'address_line' => null,
            'city' => 'Surabaya',
            'province' => null,
            'postal_code' => null,
        ], $profile->resolvedAddress());
    }

    // ── penjaga ─────────────────────────────────────────────────────────────

    /**
     * Reputasi tidak boleh datang dari payload.
     *
     * Ini bug yang sudah pernah terjadi sungguhan di `users.status`: nilai yang
     * jatuh dari mass assignment hilang TANPA GALAT, jadi yang menentukan
     * hasilnya adalah bawaan kolomnya.
     */
    public function test_reputation_is_not_mass_assignable(): void
    {
        $user = User::factory()->create();

        $profile = $user->workerProfile()->create([
            'display_name' => 'Budi',
            'worker_rating_avg' => 5,
            'worker_rating_count' => 999,
            'tasks_completed' => 999,
            'bids_won' => 999,
        ]);

        $this->assertSame('Budi', $profile->display_name);
        $this->assertSame(0.0, (float) $profile->refresh()->worker_rating_avg);
        $this->assertSame(0, $profile->worker_rating_count);
        $this->assertSame(0, $profile->tasks_completed);
        $this->assertSame(0, $profile->bids_won);
    }

    /** Satu profil per orang, dijamin basis data. */
    public function test_one_worker_profile_per_user(): void
    {
        $user = User::factory()->create();

        $user->workerProfile()->create([]);

        $this->expectException(UniqueConstraintViolationException::class);

        $user->workerProfile()->create([]);
    }

    /**
     * Penyaring alamat hanya mengenal kolom yang ada di KEDUA tabel.
     *
     * Nilai dari klien tidak pernah sampai ke nama kolom — `ListWorkersData`
     * hanya bisa mengisi `city` atau `province`. Penjaga ini untuk pemanggil
     * berikutnya: nama kolom yang salah ketik akan berakhir sebagai SQL yang
     * menyaring kolom tidak dikenal, dan yang terlihat cuma daftar kosong.
     */
    public function test_the_address_filter_refuses_a_column_it_does_not_know(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('kolom alamat tidak dikenal: bio');

        UserWorker::query()->whereResolvedAddress('bio', 'apa pun')->get();
    }

    /** Jalur BACA tidak boleh menulis baris. */
    public function test_reading_the_profile_never_creates_a_row(): void
    {
        $user = User::factory()->create();

        $profile = $user->workerProfileOrNew();

        $this->assertFalse($profile->exists);
        $this->assertSame(0, UserWorker::query()->where('user_id', $user->getKey())->count());
        // Tetap bisa diresolusi walau belum tersimpan.
        $this->assertSame($user->name, $profile->resolvedName());
        $this->assertSame(0, $profile->tasks_completed);
    }

    public function test_the_write_path_creates_the_row_once(): void
    {
        $user = User::factory()->create();

        $first = $user->workerProfileOrCreate();
        $second = $user->workerProfileOrCreate();

        $this->assertTrue($first->exists);
        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(1, UserWorker::query()->where('user_id', $user->getKey())->count());
    }
}
