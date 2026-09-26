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

    private function entry(User $user, WalletEntryType $type, int $amount, string $at): void
    {
        $ledger = app(WalletLedger::class);

        $row = $type->direction()->value === 'credit'
            ? $ledger->credit($ledger->walletFor($user), $type, $amount, null, 'x')
            : $ledger->debit($ledger->walletFor($user), $type, $amount, null, 'x');

        WalletEntry::query()->whereKey($row->getKey())->update(['created_at' => $at]);
    }

    /** Bulan bawaan tidak "meluap": 31 Januari → Februari, bukan Maret. */
    public function test_the_default_month_does_not_overflow(): void
    {
        // Bulan bawaan milik ZONA APLIKASI. 31 Jan 20:00 UTC = 1 Feb 03:00 WIB.
        config(['app.timezone' => 'Asia/Jakarta']);

        [$from, $to] = WalletSummaryQueryData::currentMonth(
            CarbonImmutable::parse('2027-01-31 20:00:00', 'UTC'),
        );

        $this->assertSame('2027-02-01T00:00:00+07:00', $from->toIso8601String());
        $this->assertSame('2027-03-01T00:00:00+07:00', $to->toIso8601String());
    }

    public function test_totals_split_by_direction_and_type(): void
    {
        $user = User::factory()->create();
        $this->entry($user, WalletEntryType::Earning, 40_000, '2026-09-22 01:00:00');
        $this->entry($user, WalletEntryType::Earning, 60_000, '2026-09-15 01:00:00');
        $this->entry($user, WalletEntryType::Topup, 100_000, '2026-09-16 01:00:00');
        $this->entry($user, WalletEntryType::TaskHold, 25_000, '2026-09-17 01:00:00');

        $summary = app(SummarizeWalletAction::class)->handle(
            $user,
            new WalletSummaryQueryData(
                from: CarbonImmutable::parse('2026-09-01T00:00:00+00:00'),
                to: CarbonImmutable::parse('2026-10-01T00:00:00+00:00'),
            ),
        );

        $this->assertSame(200_000, $summary->creditTotal);
        $this->assertSame(25_000, $summary->debitTotal);
        $this->assertSame(4, $summary->entriesCount);
        $this->assertSame(100_000, $summary->earningTotal);
        $this->assertSame(100_000, $summary->byType['earning']);
        $this->assertNull($summary->previousCreditTotal);
        $this->assertSame([], $summary->byMonth);
    }

    public function test_filters_narrow_the_totals(): void
    {
        $user = User::factory()->create();
        $this->entry($user, WalletEntryType::Earning, 40_000, '2026-09-22 01:00:00');
        $this->entry($user, WalletEntryType::Topup, 100_000, '2026-09-16 01:00:00');

        $summary = app(SummarizeWalletAction::class)->handle(
            $user,
            new WalletSummaryQueryData(
                from: CarbonImmutable::parse('2026-09-01T00:00:00+00:00'),
                to: CarbonImmutable::parse('2026-10-01T00:00:00+00:00'),
                types: [WalletEntryType::Earning],
            ),
        );

        $this->assertSame(40_000, $summary->creditTotal);
        $this->assertSame(0, $summary->byType['topup']);
    }

    public function test_compare_previous_sums_the_equal_period_before_the_range(): void
    {
        $user = User::factory()->create();
        $this->entry($user, WalletEntryType::Earning, 40_000, '2026-09-22 01:00:00');
        // Sebelum `from` (1 Sep) tapi sesudah `from - 30 hari` (2 Agu).
        $this->entry($user, WalletEntryType::Earning, 60_000, '2026-08-15 01:00:00');
        $this->entry($user, WalletEntryType::Topup, 100_000, '2026-08-20 01:00:00');

        $summary = app(SummarizeWalletAction::class)->handle(
            $user,
            new WalletSummaryQueryData(
                from: CarbonImmutable::parse('2026-09-01T00:00:00+00:00'),
                to: CarbonImmutable::parse('2026-10-01T00:00:00+00:00'),
                comparePrevious: true,
            ),
        );

        $this->assertSame(40_000, $summary->creditTotal);
        $this->assertSame(160_000, $summary->previousCreditTotal);
        $this->assertSame(60_000, $summary->previousEarningTotal);
    }

    public function test_group_by_month_buckets_entries_per_calendar_month(): void
    {
        $user = User::factory()->create();
        $this->entry($user, WalletEntryType::Topup, 10_000, '2026-09-05 01:00:00');
        $this->entry($user, WalletEntryType::TaskHold, 5_000, '2026-09-20 01:00:00');
        $this->entry($user, WalletEntryType::Earning, 3_000, '2026-10-03 01:00:00');

        $summary = app(SummarizeWalletAction::class)->handle(
            $user,
            new WalletSummaryQueryData(
                from: CarbonImmutable::parse('2026-09-01T00:00:00+00:00'),
                to: CarbonImmutable::parse('2026-11-01T00:00:00+00:00'),
                groupByMonth: true,
            ),
        );

        $this->assertSame([
            ['month' => '2026-09', 'credit_total' => 10_000, 'debit_total' => 5_000, 'entries_count' => 2, 'earning_total' => 0],
            ['month' => '2026-10', 'credit_total' => 3_000, 'debit_total' => 0, 'entries_count' => 1, 'earning_total' => 3_000],
        ], $summary->byMonth);
    }

    public function test_a_user_without_a_wallet_gets_zeros_and_no_row(): void
    {
        $user = User::factory()->create();

        [$from, $to] = WalletSummaryQueryData::currentMonth();

        $summary = app(SummarizeWalletAction::class)->handle(
            $user,
            new WalletSummaryQueryData(from: $from, to: $to),
        );

        $this->assertSame(0, $summary->creditTotal + $summary->debitTotal + $summary->entriesCount);
        $this->assertSame(0, array_sum($summary->byType));
        $this->assertDatabaseMissing('wallets', ['user_id' => $user->getKey()]);
    }
}
