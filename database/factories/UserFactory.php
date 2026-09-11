<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\UserActiveMode;
use App\Enums\UserStatus;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserWorker;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $first = fake()->firstName();
        $last = fake()->lastName();

        return [
            // ulid diisi eksplisit di sini, bukan hanya mengandalkan hook `creating`:
            // hook mati kalau model event dinonaktifkan, dan kolomnya NOT NULL unique.
            'ulid' => (string) Str::ulid(),
            'name' => "$first $last",
            'first_name' => $first,
            'last_name' => $last,
            'username' => fake()->unique()->userName(),
            'gender' => fake()->randomElement(Gender::cases()),
            // Rentang umur yang sah menurut aturan validasi (17-100 tahun),
            // supaya data buatan factory tidak pernah jadi data yang API-nya
            // sendiri akan tolak.
            'birth_date' => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => fake()->unique()->numerify('+628##########'),
            'phone_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'active_mode' => UserActiveMode::Hiring,
            'status' => UserStatus::Active,
            'city' => fake()->city(),
            'province' => 'DKI Jakarta',
            'postal_code' => fake()->numerify('#####'),
            'theme' => 'system',
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (): array => ['email_verified_at' => null]);
    }

    /** Sedang di mode cari kerja. */
    public function working(): static
    {
        return $this->state(fn (): array => ['active_mode' => UserActiveMode::Working]);
    }

    /** Sedang di mode cari bantuan. */
    public function hiring(): static
    {
        return $this->state(fn (): array => ['active_mode' => UserActiveMode::Hiring]);
    }

    /**
     * Punya reputasi sebagai penerima kerja.
     *
     * Lewat afterCreating, sama seperti `withSkills`: sejak reputasi pindah
     * ke `user_workers`, angkanya bukan kolom di baris ini lagi dan tidak bisa
     * ikut pada INSERT users.
     */
    public function experiencedWorker(): static
    {
        return $this->afterCreating(function (User $user): void {
            UserWorker::factory()->experienced()->create(['user_id' => $user->getKey()]);

            // Relasi yang sudah terlanjur termuat (User::$with) akan basi
            // kalau tidak dibuang — test yang membaca $user->workerProfile
            // langsung sesudah membuat akan melihat null.
            $user->unsetRelation('workerProfile');
        });
    }

    /** Belum pernah mengisi apa pun sebagai pekerja. */
    public function withWorkerProfile(): static
    {
        return $this->afterCreating(function (User $user): void {
            UserWorker::factory()->create(['user_id' => $user->getKey()]);

            $user->unsetRelation('workerProfile');
        });
    }

    /**
     * Lampirkan keahlian berdasarkan slug.
     *
     * Lewat afterCreating, bukan state: keahlian sudah bukan kolom melainkan
     * relasi pivot, jadi tidak bisa diikutkan pada INSERT users.
     *
     * @param  list<string>  $slugs
     */
    public function withSkills(array $slugs): static
    {
        return $this->afterCreating(function (User $user) use ($slugs): void {
            $user->skills()->sync(
                Skill::query()->whereIn('slug', $slugs)->pluck('id'),
            );
        });
    }
}
