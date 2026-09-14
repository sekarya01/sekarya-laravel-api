<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Auth;

use App\Enums\Gender;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * `gender` (tiga nilai) dan `province` di pendaftaran — lewat HTTP, dengan
 * pemeriksaan sampai ke KOLOM basis data, bukan model di memori: `gender`
 * tidak mass-assignable akan dibuang tanpa galat dan endpoint tetap 202.
 */
final class RegisterGenderProvinceTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = [
        'first_name' => 'Budi',
        'last_name' => 'Prasetyo',
        'email' => 'budi@sekarya.test',
        'password' => 'RahasiaKuat2026',
        'password_confirmation' => 'RahasiaKuat2026',
        'city' => 'Jakarta Selatan',
        'province' => 'DKI Jakarta',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    /** @return array<string, array{string}> */
    public static function genderProvider(): array
    {
        return [
            'male' => ['male'],
            'female' => ['female'],
            'prefer_not_to_say' => ['prefer_not_to_say'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('genderProvider')]
    public function test_register_persists_each_accepted_gender(string $gender): void
    {
        // Respons pendaftaran sengaja minimal (tanpa token, tanpa profil),
        // jadi yang diperiksa kolomnya — bukan badan respons.
        $this->postJson(route('v1.auth.register'), [...self::PAYLOAD, 'gender' => $gender])
            ->assertAccepted();

        // Baris yang benar-benar tertulis, bukan model hasil create().
        $this->assertDatabaseHas('users', [
            'email' => 'budi@sekarya.test',
            'gender' => $gender,
        ]);
    }

    public function test_prefer_not_to_say_survives_a_full_round_trip(): void
    {
        // Nilai terpanjang (17 karakter): kolom lama VARCHAR(6) memotongnya
        // jadi "prefer", dan pembacaan berikutnya melempar saat di-cast.
        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'gender' => Gender::PreferNotToSay->value,
        ])->assertAccepted();

        $user = User::query()->where('email', 'budi@sekarya.test')->firstOrFail();

        $this->assertSame(Gender::PreferNotToSay, $user->gender);
        $this->assertSame('prefer_not_to_say', $user->getRawOriginal('gender'));
    }

    public function test_declining_to_answer_completes_the_identity_but_null_does_not(): void
    {
        $declined = new User(['gender' => Gender::PreferNotToSay, 'birth_date' => '1995-01-01']);
        $never = new User(['gender' => null, 'birth_date' => '1995-01-01']);

        // "Tidak ingin menyebutkan" adalah jawaban; belum pernah ditanya bukan.
        $this->assertTrue($declined->hasCompleteIdentity());
        $this->assertFalse($never->hasCompleteIdentity());
    }

    public function test_gender_is_optional_and_stays_null_when_omitted(): void
    {
        $this->postJson(route('v1.auth.register'), self::PAYLOAD)->assertAccepted();

        $this->assertDatabaseHas('users', [
            'email' => 'budi@sekarya.test',
            'gender' => null,
        ]);
    }

    /**
     * String kosong diperlakukan sama dengan tidak mengirim field-nya:
     * middleware ConvertEmptyStringsToNull mengubahnya jadi null sebelum
     * validasi. Klien yang mengirim `gender: ""` untuk "belum dipilih" tidak
     * akan ditolak — perilaku yang sama dengan `city`/`province`.
     */
    public function test_empty_string_gender_is_treated_as_not_answered(): void
    {
        $this->postJson(route('v1.auth.register'), [...self::PAYLOAD, 'gender' => ''])
            ->assertAccepted();

        $this->assertDatabaseHas('users', [
            'email' => 'budi@sekarya.test',
            'gender' => null,
        ]);
    }

    public function test_unknown_gender_is_rejected(): void
    {
        // String kosong TIDAK di sini: ia jadi null sebelum validasi —
        // lihat test_empty_string_gender_is_treated_as_not_answered.
        foreach (['other', 'MALE', 'laki-laki', 'prefer not to say', 'null'] as $invalid) {
            $this->postJson(route('v1.auth.register'), [
                ...self::PAYLOAD,
                'gender' => $invalid,
            ])->assertJsonValidationErrorFor('gender');
        }

        $this->assertDatabaseMissing('users', ['email' => 'budi@sekarya.test']);
    }

    public function test_province_is_persisted_like_city(): void
    {
        $this->postJson(route('v1.auth.register'), self::PAYLOAD)->assertAccepted();

        $this->assertDatabaseHas('users', [
            'email' => 'budi@sekarya.test',
            'city' => 'Jakarta Selatan',
            'province' => 'DKI Jakarta',
        ]);
    }

    public function test_province_is_optional_and_length_capped_like_city(): void
    {
        $payload = self::PAYLOAD;
        unset($payload['province']);

        $this->postJson(route('v1.auth.register'), $payload)->assertAccepted();
        $this->assertDatabaseHas('users', ['email' => 'budi@sekarya.test', 'province' => null]);

        $this->postJson(route('v1.auth.register'), [
            ...self::PAYLOAD,
            'email' => 'lain@sekarya.test',
            'province' => str_repeat('a', 81),
        ])->assertJsonValidationErrorFor('province');
    }
}
