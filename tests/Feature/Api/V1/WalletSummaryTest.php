<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletEntry;
use App\Support\WalletLedger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * `GET /me/wallet/summary` (B2) — total dijumlahkan server.
 *
 * Kontrak dokumen: `credit_total`, `debit_total`, `entries_count`, `by_type`,
 * `earning_total`; `previous` bila `compare_previous=1`; `by_month` bila
 * `group=month`.
 */
final class WalletSummaryTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->user = $this->activeUser();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** Satu baris buku besar dengan waktu yang ditentukan test (UTC). */
    private function entry(User $owner, WalletEntryType $type, int $amount, string $createdAtUtc): void
    {
        $ledger = app(WalletLedger::class);
        $wallet = $ledger->walletFor($owner);
        $row = $type->direction() === WalletEntryDirection::Credit
            ? $ledger->credit($wallet, $type, $amount, null, 'fixture')
            : $ledger->debit($wallet, $type, $amount, null, 'fixture');

        WalletEntry::query()->whereKey($row->getKey())->update(['created_at' => $createdAtUtc]);
    }

    private function seedSeptember(): void
    {
        // 31 Agu 16:59 UTC = 31 Agu 23:59 WIB → di luar September WIB.
        $this->entry($this->user, WalletEntryType::Topup, 999_000, '2026-08-31 16:59:00');
        // 31 Agu 17:00 UTC = 1 Sep 00:00 WIB → masuk (from inklusif).
        $this->entry($this->user, WalletEntryType::Topup, 250_000, '2026-08-31 17:00:00');
        $this->entry($this->user, WalletEntryType::Refund, 300_000, '2026-09-10 03:00:00');
        $this->entry($this->user, WalletEntryType::TaskHold, 230_000, '2026-09-20 03:00:00');
        // 30 Sep 17:00 UTC = 1 Okt 00:00 WIB → di luar (to eksklusif).
        $this->entry($this->user, WalletEntryType::Earning, 111_000, '2026-09-30 17:00:00');
    }

    /** @param  array<string, string>  $query */
    private function summary(array $query = []): TestResponse
    {
        return $this->asUser($this->user)->getJson(route('v1.me.wallet.summary', $query));
    }

    public function test_it_sums_the_range_by_direction_and_type(): void
    {
        $this->seedSeptember();

        $response = $this->summary([
            'from' => '2026-09-01T00:00:00+07:00',
            'to' => '2026-10-01T00:00:00+07:00',
        ])->assertOk()
            ->assertJsonPath('data.from', '2026-09-01T00:00:00+07:00')
            ->assertJsonPath('data.to', '2026-10-01T00:00:00+07:00')
            ->assertJsonPath('data.credit_total', 550_000)
            ->assertJsonPath('data.debit_total', 230_000)
            ->assertJsonPath('data.entries_count', 3)
            ->assertJsonPath('data.earning_total', 0)
            ->assertJsonPath('data.by_type.topup', 250_000)
            ->assertJsonPath('data.by_type.refund', 300_000)
            ->assertJsonPath('data.by_type.task_hold', 230_000)
            ->assertJsonPath('data.by_type.earning', 0);

        // Setiap jenis selalu ada, sebagai OBJEK — tidak pernah daftar kosong.
        $this->assertSame(
            array_map(static fn (WalletEntryType $t): string => $t->value, WalletEntryType::cases()),
            array_keys($response->json('data.by_type')),
        );
        $this->assertStringContainsString('"by_type":{', (string) $response->getContent());

        // `previous`/`by_month` hanya muncul bila diminta.
        $response->assertJsonMissingPath('data.previous');
        $response->assertJsonMissingPath('data.by_month');
    }

    /** Total server sama dengan menjumlahkan SELURUH halaman entries untuk rentang yang sama. */
    public function test_totals_match_the_entries_list_for_the_same_range(): void
    {
        $this->seedSeptember();
        $range = ['from' => '2026-09-01T00:00:00+07:00', 'to' => '2026-10-01T00:00:00+07:00'];

        $rows = $this->asUser($this->user)
            ->getJson(route('v1.me.wallet.entries.index', $range + ['per_page' => 50]))
            ->assertOk()
            ->json('data');

        $credit = array_sum(array_map(fn (array $r): int => $r['direction'] === 'credit' ? $r['amount'] : 0, $rows));
        $debit = array_sum(array_map(fn (array $r): int => $r['direction'] === 'debit' ? $r['amount'] : 0, $rows));

        $this->summary($range)->assertOk()
            ->assertJsonPath('data.credit_total', $credit)
            ->assertJsonPath('data.debit_total', $debit)
            ->assertJsonPath('data.entries_count', count($rows));
    }

    public function test_filters_make_the_totals_follow_the_active_tab(): void
    {
        $this->seedSeptember();
        $range = ['from' => '2026-09-01T00:00:00+07:00', 'to' => '2026-10-01T00:00:00+07:00'];

        $this->summary($range + ['direction' => 'credit'])->assertOk()
            ->assertJsonPath('data.credit_total', 550_000)
            ->assertJsonPath('data.debit_total', 0);

        $this->summary($range + ['types' => ['refund', 'task_hold']])->assertOk()
            ->assertJsonPath('data.credit_total', 300_000)
            ->assertJsonPath('data.debit_total', 230_000)
            ->assertJsonPath('data.by_type.topup', 0);
    }

    public function test_it_defaults_to_the_current_calendar_month(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 10:00:00');
        $this->seedSeptember();

        // Zona aplikasi UTC: yang masuk refund, task_hold, dan earning
        // 30 Sep 17:00 UTC. Topup 31 Agu 17:00 UTC masih Agustus di UTC.
        $this->summary()->assertOk()
            ->assertJsonPath('data.from', '2026-09-01T00:00:00+00:00')
            ->assertJsonPath('data.to', '2026-10-01T00:00:00+00:00')
            ->assertJsonPath('data.credit_total', 411_000)
            ->assertJsonPath('data.debit_total', 230_000)
            ->assertJsonPath('data.entries_count', 3);
    }

    /**
     * "Pendapatan minggu ini vs pekan lalu" lewat SATU panggilan: periode
     * sebelumnya = panjang yang sama, tepat sebelum `from`.
     */
    public function test_compare_previous_returns_the_prior_equal_period(): void
    {
        $this->entry($this->user, WalletEntryType::Earning, 100_000, '2026-09-22 03:00:00');
        $this->entry($this->user, WalletEntryType::Earning, 50_000, '2026-09-23 03:00:00');
        // Sebelum 1 Sep, tapi sesudah 2 Agu (from - 30 hari).
        $this->entry($this->user, WalletEntryType::Earning, 80_000, '2026-08-14 03:00:00');
        // Lebih tua dari periode pembanding → tidak dihitung.
        $this->entry($this->user, WalletEntryType::Earning, 70_000, '2026-08-01 03:00:00');

        $this->summary([
            'from' => '2026-09-01T00:00:00+00:00',
            'to' => '2026-10-01T00:00:00+00:00',
            'compare_previous' => 1,
        ])->assertOk()
            ->assertJsonPath('data.credit_total', 150_000)
            ->assertJsonPath('data.earning_total', 150_000)
            ->assertJsonPath('data.previous.credit_total', 80_000)
            ->assertJsonPath('data.previous.earning_total', 80_000);
    }

    public function test_group_by_month_returns_a_monthly_series(): void
    {
        $this->entry($this->user, WalletEntryType::Topup, 10_000, '2026-09-05 03:00:00');
        $this->entry($this->user, WalletEntryType::TaskHold, 5_000, '2026-09-20 03:00:00');
        $this->entry($this->user, WalletEntryType::Earning, 3_000, '2026-10-03 03:00:00');

        $byMonth = $this->summary([
            'from' => '2026-09-01T00:00:00+00:00',
            'to' => '2026-11-01T00:00:00+00:00',
            'group' => 'month',
        ])->assertOk()->json('data.by_month');

        $this->assertSame([
            ['month' => '2026-09', 'credit_total' => 10_000, 'debit_total' => 5_000, 'entries_count' => 2, 'earning_total' => 0],
            ['month' => '2026-10', 'credit_total' => 3_000, 'debit_total' => 0, 'entries_count' => 1, 'earning_total' => 3_000],
        ], $byMonth);
    }

    public function test_an_unknown_group_is_refused(): void
    {
        $this->summary(['group' => 'week'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group');
    }

    /** Orang lain tidak bisa dibaca — tidak ada parameter pemilik, dan barisnya tidak ikut. */
    public function test_another_users_entries_never_count(): void
    {
        $other = $this->activeUser();
        $this->entry($other, WalletEntryType::Topup, 700_000, '2026-09-10 03:00:00');
        $this->entry($other, WalletEntryType::Earning, 90_000, '2026-09-22 03:00:00');
        $this->entry($this->user, WalletEntryType::Topup, 10_000, '2026-09-10 03:00:00');

        $this->summary(['user_id' => (string) $other->getKey(), 'wallet_id' => '1'])->assertOk()
            ->assertJsonPath('data.credit_total', 10_000)
            ->assertJsonPath('data.entries_count', 1)
            ->assertJsonPath('data.earning_total', 0);

        $this->asUser($other)->getJson(route('v1.me.wallet.summary'))->assertOk()
            ->assertJsonPath('data.credit_total', 790_000)
            ->assertJsonPath('data.earning_total', 90_000);
    }

    /** Jalur baca tidak membuat dompet; akun kosong mendapat bentuk yang sama, nol semua. */
    public function test_a_fresh_account_gets_zeros_and_no_wallet_row(): void
    {
        $this->summary()->assertOk()
            ->assertJsonPath('data.credit_total', 0)
            ->assertJsonPath('data.debit_total', 0)
            ->assertJsonPath('data.entries_count', 0)
            ->assertJsonPath('data.earning_total', 0)
            ->assertJsonPath('data.by_type.topup', 0);

        $this->assertSame(0, Wallet::query()->where('user_id', $this->user->getKey())->count());
    }

    public function test_it_requires_an_access_token(): void
    {
        $this->getJson(route('v1.me.wallet.summary'))->assertUnauthorized();

        $this->asUserWithLongLived($this->user)
            ->getJson(route('v1.me.wallet.summary'))
            ->assertForbidden();
    }

    public function test_invalid_ranges_are_refused(): void
    {
        $cases = [
            'from without to' => [['from' => '2026-09-01T00:00:00+07:00'], 'to'],
            'to without from' => [['to' => '2026-09-01T00:00:00+07:00'], 'from'],
            'to before from' => [['from' => '2026-09-02T00:00:00+07:00', 'to' => '2026-09-01T00:00:00+07:00'], 'to'],
            'longer than 366 days' => [['from' => '2025-01-01T00:00:00+07:00', 'to' => '2026-01-03T00:00:00+07:00'], 'to'],
            'not a date' => [['from' => 'kemarin', 'to' => '2026-09-01T00:00:00+07:00'], 'from'],
            'unknown type' => [['types' => ['hadiah']], 'types.0'],
            'unknown direction' => [['direction' => 'masuk'], 'direction'],
        ];

        foreach ($cases as $label => [$query, $field]) {
            $this->summary($query)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field, 'errors', $label);
        }
    }

    public function test_exactly_366_days_is_allowed(): void
    {
        $this->summary(['from' => '2025-01-01T00:00:00+07:00', 'to' => '2026-01-02T00:00:00+07:00'])
            ->assertOk();
    }

    /**
     * Kueri ringkasan bertumpu pada indeks dompet, bukan memindai tabel.
     * Diperiksa dengan volume yang cukup supaya pengoptimal tidak wajar
     * memilih pemindaian penuh karena tabelnya kecil.
     */
    public function test_the_range_sum_uses_the_wallet_index(): void
    {
        $others = [];
        foreach (range(1, 20) as $_) {
            $others[] = app(WalletLedger::class)->walletFor($this->activeUser());
        }
        $mine = app(WalletLedger::class)->walletFor($this->user);

        $rows = [];
        foreach (range(1, 3000) as $i) {
            $wallet = $i % 25 === 0 ? $mine : $others[$i % 20];
            $rows[] = [
                'ulid' => strtoupper(substr(md5((string) $i), 0, 26)),
                'wallet_id' => $wallet->getKey(),
                'type' => 'earning',
                'direction' => 'credit',
                'amount' => 1000,
                'balance_after' => 1000,
                'created_at' => CarbonImmutable::parse('2026-01-01')->addHours($i)->toDateTimeString(),
            ];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('wallet_entries')->insert($chunk);
        }
        DB::statement('ANALYZE TABLE wallet_entries');

        $query = WalletEntry::query()->toBase()
            ->where('wallet_id', $mine->getKey())
            ->where('created_at', '>=', '2026-02-01 00:00:00')
            ->where('created_at', '<', '2026-03-01 00:00:00')
            ->groupBy('type')
            ->selectRaw('type, SUM(amount) AS total, COUNT(*) AS entries');

        // FORMAT=TRADITIONAL eksplisit: MySQL 8.3+ bisa berbawaan TREE, yang
        // hanya punya satu kolom teks.
        $plan = collect(DB::select('EXPLAIN FORMAT=TRADITIONAL '.$query->toSql(), $query->getBindings()))->first();

        $this->assertNotSame('ALL', $plan->type, 'ringkasan memindai seluruh wallet_entries');
        $this->assertContains($plan->key, [
            'wallet_entries_wallet_id_created_at_id_index',
            'wallet_entries_wallet_id_type_index',
        ]);
    }
}
