<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserVerification>
 */
final class UserVerificationFactory extends Factory
{
    protected $model = UserVerification::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // NIK palsu untuk pengembangan. Path foto menunjuk storage privat.
        $nik = fake()->numerify('################');

        return [
            'user_id' => User::factory(),
            'type' => VerificationType::Identity,
            'status' => VerificationStatus::Pending,
            'id_card_photo_path' => 'verifications/'.fake()->uuid().'-ktp.jpg',
            'selfie_photo_path' => 'verifications/'.fake()->uuid().'-selfie.jpg',
            'document_number_hash' => hash('sha256', $nik),
            'document_number_enc' => $nik,
            'name_on_document' => fake()->name(),
            'submitted_at' => now(),
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => VerificationStatus::Verified,
            'face_match_score' => 96.50,
            'reviewed_at' => now(),
        ]);
    }
}
