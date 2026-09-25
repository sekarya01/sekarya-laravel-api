<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Wallet;

use App\Actions\Wallet\SummarizeWalletAction;
use App\Data\Wallet\WalletSummaryQueryData;
use App\Enums\WalletEntryType;
use App\Models\User;
use App\Models\WalletEntry;
use App\Support\WalletLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** DTO dibangun langsung — Action tidak tahu HTTP. */
final class SummarizeWalletActionTest extends TestCase
{
    use RefreshDatabase;

    private function credit(User $user, WalletEntryType $type, int $amount, string $at): void
    {
        $ledger = app(WalletLedger::class);
        $row = $ledger->credit($ledger->walletFor($user), $type, $amount, null, 'x');
        WalletEntry::query()->whereKey($row->getKey())->update(['created_at' => $at]);
    }

    /** Bulan bawaan tidak "meluap": 31 Januari → Februari, bukan Maret. */
    public function test_the_default_month_does_not_overflow(): void
    {
        $data = WalletSummaryQueryData::currentMonth('Asia/Jakarta', CarbonImmutable::parse('2027-01-31 20:00:00', 'UTC'));

        // 31 Jan 20:00 UTC = 1 Feb 03:00 WIB → bulan Februari WIB.
        $this->assertSame('2027-02-01T00:00:00+07:00', $data->from->toIso8601String());
        $this->assertSame('2027-03-01T00:00:00+07:00', $data->to->toIso8601String());
    }

    public function test_totals_split_by_direction_and_weeks_by_monday(): void
    {
        $user = User::factory()->create();
        $this->credit($user, WalletEntryType::Earning, 40_000, '2026-09-22 01:00:00');
        $this->credit($user, WalletEntryType::Earning, 60_000, '2026-09-15 01:00:00');
        $this->credit($user, WalletEntryType::Topup, 100_000, '2026-09-16 01:00:00');

        $summary = app(SummarizeWalletAction::class)->handle(
            $user,
            new WalletSummaryQueryData(
                from: CarbonImmutable::parse('2026-09-01T00:00:00+00:00'),
                to: CarbonImmutable::parse('2026-10-01T00:00:00+00:00'),
                timezone: 'UTC',
            ),
            CarbonImmutable::parse('2026-09-23 12:00:00', 'UTC'),
        );

        $this->assertSame(200_000, $summary->totalIn);
        $this->assertSame(0, $summary->totalOut);
        $this->assertSame(3, $summary->count);
        $this->assertSame(100_000, $summary->byType['earning']);
        $this->assertSame('2026-09-21T00:00:00+00:00', $summary->weekStart->toIso8601String());
        $this->assertSame(40_000, $summary->earningsThisWeek);
        $this->assertSame(60_000, $summary->earningsLastWeek);
    }

    public function test_a_user_without_a_wallet_gets_zeros_and_no_row(): void
    {
        $user = User::factory()->create();

        $summary = app(SummarizeWalletAction::class)->handle(
            $user,
            WalletSummaryQueryData::currentMonth('UTC'),
        );

        $this->assertSame(0, $summary->totalIn + $summary->totalOut + $summary->count);
        $this->assertSame(0, array_sum($summary->byType));
        $this->assertDatabaseMissing('wallets', ['user_id' => $user->getKey()]);
    }
}
