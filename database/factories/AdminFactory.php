<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Admin>
 */
final class AdminFactory extends Factory
{
    protected $model = Admin::class;

    protected static ?string $password;

    /**
     * Bawaannya peran `admin`, BUKAN super_admin.
     *
     * Dua alasan. Pertama, super_admin dibatasi satu baris oleh indeks unique
     * di basis data, jadi factory yang bawaannya super_admin akan gagal pada
     * pemanggilan kedua di test yang sama. Kedua, test yang tanpa sengaja
     * memakai super_admin akan lulus untuk alasan yang salah: perannya boleh
     * segalanya, sehingga pemeriksaan izin apa pun ikut lolos.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Diisi eksplisit, tidak hanya mengandalkan hook `creating`: hook
            // mati kalau model event dinonaktifkan, dan kolomnya NOT NULL unique.
            'ulid' => (string) Str::ulid(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),

            // Factory MELEWATI $fillable, jadi kolom berhak pun tertulis.
            // Justru karena itu kolom ini harus disetel sadar di sini — dan
            // justru karena itu juga jalur nyata (Action, command) harus diuji
            // terpisah: bug "nilai hilang tanpa galat" tidak akan pernah
            // muncul lewat factory.
            'role' => AdminRole::Admin,
            'status' => AdminStatus::Active,
        ];
    }

    /** Satu-satunya, tidak bisa dihapus. Paling banyak SATU baris di basis data. */
    public function superAdmin(): static
    {
        return $this->state(fn (): array => ['role' => AdminRole::SuperAdmin]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => AdminStatus::Suspended]);
    }
}
