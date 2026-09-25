<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /me/wallet/config` (B1) — rekening tujuan + batas nominal.
 *
 * Rekeningnya dari env; yang diuji di sini adalah bentuk respons dan bahwa
 * limit mengikuti config, bukan angka yang ditulis ulang di klien.
 */
final class WalletConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_requires_an_access_token(): void
    {
        $this->getJson(route('v1.me.wallet.config'))->assertUnauthorized();

        $this->asUserWithLongLived($this->activeUser())
            ->getJson(route('v1.me.wallet.config'))
            ->assertForbidden();
    }

    public function test_it_reports_the_limits_from_config(): void
    {
        $response = $this->asUser($this->activeUser())
            ->getJson(route('v1.me.wallet.config'))
            ->assertOk()
            ->assertJsonPath('data.limits.min_topup', (int) config('sekarya.wallet.min_topup'))
            ->assertJsonPath('data.limits.max_topup', (int) config('sekarya.wallet.max_topup'))
            ->assertJsonPath('data.limits.min_withdrawal', (int) config('sekarya.wallet.min_withdrawal'))
            ->assertJsonPath('data.limits.max_withdrawal', (int) config('sekarya.wallet.max_withdrawal'))
            ->assertJsonPath('data.limits.max_pending_requests', (int) config('sekarya.wallet.max_pending_requests'))
            // Tanpa env: tidak ada rekening, tetapi bentuknya tetap larik.
            ->assertJsonPath('data.topup_accounts', []);

        // `limits` selalu objek, tidak pernah larik.
        $this->assertStringContainsString('"limits":{', (string) $response->getContent());
    }

    public function test_it_reports_the_configured_topup_accounts(): void
    {
        config(['sekarya.wallet.topup_accounts' => [[
            'bank_code' => 'BCA',
            'bank_name' => 'Bank Central Asia',
            'account_number' => '1234567890',
            'account_holder' => 'PT Sekarya Digital',
        ]]]);

        $this->asUser($this->activeUser())
            ->getJson(route('v1.me.wallet.config'))
            ->assertOk()
            ->assertJsonPath('data.topup_accounts.0.bank_code', 'BCA')
            ->assertJsonPath('data.topup_accounts.0.bank_name', 'Bank Central Asia')
            ->assertJsonPath('data.topup_accounts.0.account_number', '1234567890')
            ->assertJsonPath('data.topup_accounts.0.account_holder', 'PT Sekarya Digital');
    }
}
