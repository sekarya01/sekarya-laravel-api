<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserActiveMode;
use App\Enums\UserStatus;
use App\Models\Skill;
use App\Models\User;
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
        return [
            // ulid diisi eksplisit di sini, bukan hanya mengandalkan hook `creating`:
            // hook mati kalau model event dinonaktifkan, dan kolomnya NOT NULL unique.
            'ulid' => (string) Str::ulid(),
            'name' => fake()->name(),
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

    /** Punya reputasi sebagai penerima kerja. */
    public function experiencedWorker(): static
    {
        return $this->state(fn (): array => [
            'worker_rating_avg' => 4.9,
            'worker_rating_count' => 214,
            'tasks_completed' => 214,
            'bids_won' => 230,
        ]);
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
