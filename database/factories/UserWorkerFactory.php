<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserWorker;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserWorker>
 */
class UserWorkerFactory extends Factory
{
    /**
     * Bawaannya SEPI: profil pekerja yang tidak menimpa apa pun.
     *
     * Itu keadaan yang paling sering terjadi sungguhan — orang mulai bekerja
     * tanpa pernah membuka formulir profil pekerjanya, dan seluruh datanya
     * diwarisi dari akun. Test yang butuh nilai timpaan menyebutkannya sendiri;
     * kalau bawaannya justru mengisi semuanya, jalur warisan itu tidak akan
     * pernah teruji oleh satu test pun yang tidak sengaja mengujinya.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
        ];
    }

    /** Punya identitas pekerja sendiri, berbeda dari akunnya. */
    public function withOwnIdentity(): static
    {
        return $this->state(fn (): array => [
            'display_name' => fake()->name(),
            'contact_phone' => fake()->unique()->numerify('+628##########'),
            'avatar_path' => 'avatars/'.fake()->uuid().'.jpg',
        ]);
    }

    /** Punya alamat kerja sendiri, berbeda dari domisili akunnya. */
    public function withOwnAddress(): static
    {
        return $this->state(fn (): array => [
            'address_line' => fake()->streetAddress(),
            'city' => fake()->city(),
            'province' => 'Jawa Barat',
            'postal_code' => fake()->numerify('#####'),
        ]);
    }

    /** Terpasang di peta, dengan radius jangkauan. */
    public function locatedInJakarta(): static
    {
        return $this->state(fn (): array => [
            'latitude' => fake()->randomFloat(7, -6.35, -6.10),
            'longitude' => fake()->randomFloat(7, 106.70, 106.95),
            'radius_km' => fake()->numberBetween(5, 40),
        ]);
    }

    /**
     * Reputasi. Boleh lewat state biasa walaupun kolomnya tidak
     * mass-assignable: factory membuat modelnya di dalam `Model::unguarded()`.
     */
    public function experienced(): static
    {
        return $this->state(fn (): array => [
            'worker_rating_avg' => 4.9,
            'worker_rating_count' => 214,
            'tasks_completed' => 214,
            'bids_won' => 230,
        ]);
    }
}
