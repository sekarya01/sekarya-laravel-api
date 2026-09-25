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
            ->assertJsonPath('data.total_in', 550_000)
            ->assertJsonPath('data.total_out', 230_000)
            ->assertJsonPath('data.count', 3)
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

        $in = array_sum(array_map(fn (array $r): int => $r['direction'] === 'credit' ? $r['amount'] : 0, $rows));
        $out = array_sum(array_map(fn (array $r): int => $r['direction'] === 'debit' ? $r['amount'] : 0, $rows));

        $this->summary($range)->assertOk()
            ->assertJsonPath('data.total_in', $in)
            ->assertJsonPath('data.total_out', $out)
            ->assertJsonPath('data.count', count($rows));
    }

    public function test_it_defaults_to_the_current_calendar_month(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 10:00:00');
        $this->seedSeptember();

        // Zona aplikasi UTC: September UTC memuat baris 31 Agu 17:00 UTC? Tidak —
        // itu masih Agustus di UTC. Yang masuk: refund, task_hold, dan earning
        // 30 Sep 17:00 UTC.
        $this->summary()->assertOk()
            ->assertJsonPath('data.from', '2026-09-01T00:00:00+00:00')
            ->assertJsonPath('data.to', '2026-10-01T00:00:00+00:00')
            ->assertJsonPath('data.total_in', 411_000)
            ->assertJsonPath('data.total_out', 230_000)
            ->assertJsonPath('data.count', 3);

        // Dengan tz, bulan bawaannya milik orangnya.
        $this->summary(['tz' => 'Asia/Jakarta'])->assertOk()
            ->assertJsonPath('data.from', '2026-09-01T00:00:00+07:00')
            ->assertJsonPath('data.to', '2026-10-01T00:00:00+07:00')
            ->assertJsonPath('data.total_in', 550_000)
            ->assertJsonPath('data.count', 3);
    }

    public function test_it_reports_this_and_last_weeks_earnings(): void
    {
        // Kamis 24 Sep 2026, 10:00 WIB. Minggu ini = Senin 21 Sep 00:00 WIB.
        CarbonImmutable::setTestNow('2026-09-24 03:00:00');

        // Senin 21 Sep 00:30 WIB (20 Sep 17:30 UTC) → minggu ini, walau di UTC masih Minggu.
        $this->entry($this->user, WalletEntryType::Earning, 100_000, '2026-09-20 17:30:00');
        $this->entry($this->user, WalletEntryType::Earning, 50_000, '2026-09-23 05:00:00');
        // Pekan lalu (Senin 14 Sep WIB).
        $this->entry($this->user, WalletEntryType::Earning, 80_000, '2026-09-14 02:00:00');
        // Dua pekan lalu → tidak dihitung di mana pun.
        $this->entry($this->user, WalletEntryType::Earning, 70_000, '2026-09-06 02:00:00');
        // Bukan upah → tidak dihitung walau masuk minggu ini.
        $this->entry($this->user, WalletEntryType::Topup, 500_000, '2026-09-22 02:00:00');

        $this->summary(['tz' => 'Asia/Jakarta'])->assertOk()
            ->assertJsonPath('data.week_start', '2026-09-21T00:00:00+07:00')
            ->assertJsonPath('data.earnings_this_week', 150_000)
            ->assertJsonPath('data.earnings_last_week', 80_000);

        // Di UTC, 20 Sep 17:30 masih Minggu → pindah ke pekan lalu.
        $this->summary()->assertOk()
            ->assertJsonPath('data.week_start', '2026-09-21T00:00:00+00:00')
            ->assertJsonPath('data.earnings_this_week', 50_000)
            ->assertJsonPath('data.earnings_last_week', 180_000);
    }

    /** Orang lain tidak bisa dibaca — tidak ada parameter pemilik, dan barisnya tidak ikut. */
    public function test_another_users_entries_never_count(): void
    {
        CarbonImmutable::setTestNow('2026-09-24 03:00:00');
        $other = $this->activeUser();
        $this->entry($other, WalletEntryType::Topup, 700_000, '2026-09-10 03:00:00');
        $this->entry($other, WalletEntryType::Earning, 90_000, '2026-09-22 03:00:00');
        $this->entry($this->user, WalletEntryType::Topup, 10_000, '2026-09-10 03:00:00');

        $this->summary(['user_id' => (string) $other->getKey(), 'wallet_id' => '1'])->assertOk()
            ->assertJsonPath('data.total_in', 10_000)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.earnings_this_week', 0);

        $this->asUser($other)->getJson(route('v1.me.wallet.summary'))->assertOk()
            ->assertJsonPath('data.total_in', 790_000)
            ->assertJsonPath('data.earnings_this_week', 90_000);
    }

    /** Jalur baca tidak membuat dompet; akun kosong mendapat bentuk yang sama, nol semua. */
    public function test_a_fresh_account_gets_zeros_and_no_wallet_row(): void
    {
        $this->summary()->assertOk()
            ->assertJsonPath('data.total_in', 0)
            ->assertJsonPath('data.total_out', 0)
            ->assertJsonPath('data.count', 0)
            ->assertJsonPath('data.by_type.topup', 0)
            ->assertJsonPath('data.earnings_this_week', 0)
            ->assertJsonPath('data.earnings_last_week', 0);

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
            'unknown timezone' => [['tz' => 'Mars/Olympus'], 'tz'],
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
