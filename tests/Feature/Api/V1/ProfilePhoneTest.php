<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `PATCH /me` menerima `phone` (U5).
 */
final class ProfilePhoneTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->user = $this->activeUser(['phone' => '+6281200000001']);
        $this->user->forceFill(['phone_verified_at' => now()])->save();
    }

    private function storedPhone(): array
    {
        $row = DB::table('users')->where('id', $this->user->getKey())->first(['phone', 'phone_verified_at']);

        return [$row->phone, $row->phone_verified_at];
    }

    public function test_changing_the_phone_saves_it_and_drops_the_verified_flag(): void
    {
        $this->asUser($this->user)->patchJson(route('v1.me.update'), ['phone' => '+6281234567890'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+6281234567890')
            ->assertJsonPath('data.phone_verified', false);

        [$phone, $verifiedAt] = $this->storedPhone();
        $this->assertSame('+6281234567890', $phone);
        $this->assertNull($verifiedAt);
    }

    /** Mengirim ulang nomor yang sama bukan perubahan — status terverifikasinya tetap. */
    public function test_resending_the_same_phone_keeps_it_verified(): void
    {
        $this->asUser($this->user)->patchJson(route('v1.me.update'), ['phone' => '+6281200000001'])
            ->assertOk()
            ->assertJsonPath('data.phone_verified', true);

        $this->assertNotNull($this->storedPhone()[1]);
    }

    /** Sunting ruas lain tidak menyentuh nomor maupun statusnya. */
    public function test_other_edits_leave_the_phone_alone(): void
    {
        $this->asUser($this->user)->patchJson(route('v1.me.update'), ['bio' => 'Halo'])
            ->assertOk()
            ->assertJsonPath('data.phone', '+6281200000001')
            ->assertJsonPath('data.phone_verified', true);
    }

    public function test_a_phone_used_by_someone_else_is_refused(): void
    {
        $this->activeUser(['phone' => '+6281299999999']);

        $this->asUser($this->user)->patchJson(route('v1.me.update'), ['phone' => '+6281299999999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');

        $this->assertSame(['+6281200000001'], [$this->storedPhone()[0]]);
    }

    public function test_the_registration_format_rule_applies(): void
    {
        foreach (['0812-3456-7890', 'abc', '12345678', '+'.str_repeat('1', 20)] as $bad) {
            $this->asUser($this->user)->patchJson(route('v1.me.update'), ['phone' => $bad])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('phone');
        }

        $this->asUser($this->user)->patchJson(route('v1.me.update'), ['phone' => '081234567890'])
            ->assertOk()
            ->assertJsonPath('data.phone', '081234567890');
    }

    /** `null` mengosongkan nomor, dan status terverifikasinya ikut hilang. */
    public function test_null_clears_the_phone(): void
    {
        $this->asUser($this->user)->patchJson(route('v1.me.update'), ['phone' => null])
            ->assertOk()
            ->assertJsonPath('data.phone', null)
            ->assertJsonPath('data.phone_verified', false);

        $this->assertSame([null, null], $this->storedPhone());
    }

    /** `phone_verified_at` tidak bisa disetel dari payload. */
    public function test_the_verified_flag_cannot_be_sent(): void
    {
        $this->asUser($this->user)->patchJson(route('v1.me.update'), [
            'phone' => '+6281234567890',
            'phone_verified_at' => now()->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.phone_verified', false);

        $this->assertNull($this->storedPhone()[1]);
    }
}
