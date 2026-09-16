<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VerificationType;
use App\Enums\WalletWithdrawalStatus;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\WalletWithdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletWithdrawal>
 */
final class WalletWithdrawalFactory extends Factory
{
    protected $model = WalletWithdrawal::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(50, 500) * 1000,
            'status' => WalletWithdrawalStatus::Requested,
            // Rekening tujuan wajib ada dan wajib sudah disetujui — itu
            // aturan `RequestWithdrawalAction`, dan factory yang membuat
            // baris tanpa rekening akan menghasilkan keadaan yang tidak bisa
            // lahir dari API.
            'verification_id' => UserVerification::factory()->verified()->state([
                'type' => VerificationType::BankAccount,
                'bank_code' => 'BCA',
                'account_holder_name' => fake()->name(),
            ]),
        ];
    }
}
