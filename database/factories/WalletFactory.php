<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Wallet>
 */
final class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'user_id' => User::factory(),
            // Bawaannya NOL, dan dompet bersaldo langsung tidak disediakan
            // sebagai state. Saldo yang lahir tanpa baris buku besar adalah
            // keadaan yang tidak bisa terjadi di aplikasi — test yang
            // berangkat dari sana membuktikan sesuatu tentang sistem lain.
            // Isi saldo di test lewat WalletLedger, seperti kode sungguhan.
            'balance' => 0,
        ];
    }
}
