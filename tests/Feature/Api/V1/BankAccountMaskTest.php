<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\VerificationType;
use App\Models\User;
use App\Models\UserVerification;
use App\Support\BankAccountLast4Backfill;
use App\Support\BankAccountNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Rekening tujuan tersamar (U4): "•••• 4910", nomor utuh tidak pernah keluar.
 */
final class BankAccountMaskTest extends TestCase
{
    use RefreshDatabase;

    private const string NUMBER = '0987654910';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->user = $this->activeUser();
    }

    private function submitBankAccount(string $number = self::NUMBER): void
    {
        $this->asUser($this->user)->postJson(route('v1.me.verifications.store'), [
            'type' => 'bank_account',
            'bank_code' => 'BCA',
            'account_number' => $number,
            'account_holder_name' => 'Ratna Dewi',
        ])->assertSuccessful();
    }

    public function test_submitting_stores_the_last_four_digits(): void
    {
        $this->submitBankAccount('0987-654-910');

        $row = DB::table('user_worker_verifications')
            ->where('user_id', $this->user->getKey())
            ->where('type', VerificationType::BankAccount->value)
            ->first();

        // Dibaca dari BARIS, bukan dari model: kolom yang jatuh dari
        // $fillable hilang tanpa galat.
        $this->assertSame('4910', $row->account_number_last4);
        // Nomor utuhnya tetap terenkripsi, bukan teks biasa.
        $this->assertStringNotContainsString('0987-654-910', (string) $row->account_number_enc);
    }

    public function test_my_verifications_show_only_the_masked_number(): void
    {
        $this->submitBankAccount();

        $response = $this->asUser($this->user)->getJson(route('v1.me.verifications.index'))->assertOk();
        $bank = collect($response->json('data'))->firstWhere('type', 'bank_account');

        $this->assertSame('•••• 4910', $bank['account_number_masked']);
        $this->assertSame('BCA', $bank['bank_code']);
        $this->assertStringNotContainsString(self::NUMBER, (string) $response->getContent());
        $this->assertStringNotContainsString('account_number_enc', (string) $response->getContent());
    }

    /** Verifikasi identitas tidak punya kunci rekening sama sekali. */
    public function test_identity_verifications_have_no_account_key(): void
    {
        $this->verifyIdentity($this->user);

        $identity = collect($this->asUser($this->user)->getJson(route('v1.me.verifications.index'))
            ->assertOk()->json('data'))->firstWhere('type', 'identity');

        $this->assertArrayNotHasKey('account_number_masked', $identity);
    }

    public function test_withdrawals_carry_the_masked_destination(): void
    {
        $this->verifyBankAccount($this->user);
        $this->fundWallet($this->user, 500_000);

        $created = $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 100_000])
            ->assertCreated()
            ->assertJsonPath('data.destination.account_number_masked', '•••• 7890');
        $this->assertStringNotContainsString('1234567890', (string) $created->getContent());

        $list = $this->asUser($this->user)->getJson(route('v1.me.wallet.withdrawals.index'))
            ->assertOk()
            ->assertJsonPath('data.0.destination.account_number_masked', '•••• 7890');
        $this->assertStringNotContainsString('1234567890', (string) $list->getContent());
    }

    public function test_the_admin_withdrawal_queue_shows_the_mask_but_not_the_number(): void
    {
        $this->verifyBankAccount($this->user);
        $this->fundWallet($this->user, 500_000);
        $this->asUser($this->user)
            ->postJson(route('v1.me.wallet.withdrawals.store'), ['amount' => 100_000])
            ->assertCreated();

        $body = $this->asAdmin($this->activeAdmin())
            ->getJson(route('v1.admin.wallet.withdrawals.index'))
            ->assertOk()
            ->assertJsonPath('data.0.destination.account_number_masked', '•••• 7890')
            ->getContent();

        $this->assertStringNotContainsString('1234567890', (string) $body);
    }

    /** Baris lama disusulkan; yang tidak bisa didekripsi dibiarkan NULL tanpa menghentikan yang lain. */
    public function test_the_backfill_fills_old_rows_and_skips_undecryptable_ones(): void
    {
        $old = UserVerification::factory()->create([
            'user_id' => $this->user->getKey(),
            'type' => VerificationType::BankAccount,
            'bank_code' => 'BNI',
            'account_number_enc' => '11 22 33 4455',
            'account_holder_name' => 'Lama',
        ]);
        $broken = UserVerification::factory()->create([
            'user_id' => $this->activeUser()->getKey(),
            'type' => VerificationType::BankAccount,
            'bank_code' => 'BRI',
            'account_number_enc' => '999',
            'account_holder_name' => 'Rusak',
        ]);
        DB::table('user_worker_verifications')->whereIn('id', [$old->id, $broken->id])
            ->update(['account_number_last4' => null]);
        DB::table('user_worker_verifications')->where('id', $broken->id)
            ->update(['account_number_enc' => 'bukan-ciphertext']);

        $this->assertSame(1, app(BankAccountLast4Backfill::class)->run());

        $this->assertSame('4455', DB::table('user_worker_verifications')->where('id', $old->id)->value('account_number_last4'));
        $this->assertNull(DB::table('user_worker_verifications')->where('id', $broken->id)->value('account_number_last4'));

        // Aman diulang.
        $this->assertSame(0, app(BankAccountLast4Backfill::class)->run());
    }

    public function test_the_mask_rules(): void
    {
        $this->assertSame('4910', BankAccountNumber::lastFour('0987-654 910'));
        $this->assertSame('12', BankAccountNumber::lastFour('12'));
        $this->assertNull(BankAccountNumber::lastFour('abc'));
        $this->assertNull(BankAccountNumber::lastFour(null));
        $this->assertSame('•••• 4910', BankAccountNumber::mask('4910'));
        $this->assertNull(BankAccountNumber::mask(null));
    }
}
