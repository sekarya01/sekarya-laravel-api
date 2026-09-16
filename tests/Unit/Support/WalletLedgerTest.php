<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Exceptions\Domain\InsufficientBalanceException;
use App\Models\User;
use App\Models\WalletTopup;
use App\Support\WalletLedger;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Buku besar saldo — satu-satunya jalur tulis saldo.
 *
 * Yang diuji di sini bukan endpoint, melainkan invarian yang tidak boleh bisa
 * dilanggar dari mana pun: saldo selalu sama dengan jumlah barisnya, debit
 * tidak pernah menembus nol, dan satu kejadian tidak pernah jadi dua baris.
 */
final class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    private WalletLedger $ledger;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = app(WalletLedger::class);
        $this->user = User::factory()->create();
    }

    public function test_a_credit_raises_the_balance_and_records_the_balance_after(): void
    {
        $wallet = $this->ledger->walletFor($this->user);

        $entry = $this->ledger->credit($wallet, WalletEntryType::Topup, 50_000);

        $this->assertSame(50_000, (int) $wallet->refresh()->balance);
        $this->assertSame(50_000, $entry->balance_after);
        $this->assertSame(WalletEntryDirection::Credit, $entry->direction);
    }

    public function test_a_debit_lowers_the_balance(): void
    {
        $wallet = $this->ledger->walletFor($this->user);
        $this->ledger->credit($wallet, WalletEntryType::Topup, 50_000);

        $entry = $this->ledger->debit($wallet, WalletEntryType::Withdrawal, 20_000);

        $this->assertSame(30_000, (int) $wallet->refresh()->balance);
        $this->assertSame(30_000, $entry->balance_after);
        $this->assertSame(WalletEntryDirection::Debit, $entry->direction);
    }

    /**
     * Instance yang dipegang pemanggil ikut segar.
     *
     * Kalau tidak, Action yang menampilkan dompetnya sesudah mutasi
     * mengembalikan saldo SEBELUM mutasi — respons yang salah atas permintaan
     * yang berhasil, dan klien yang menampilkannya tidak punya cara tahu.
     */
    public function test_the_callers_instance_sees_the_new_balance(): void
    {
        $wallet = $this->ledger->walletFor($this->user);

        $this->ledger->credit($wallet, WalletEntryType::Topup, 75_000);

        $this->assertSame(75_000, (int) $wallet->balance);
    }

    /** Invarian utama: cache saldo tidak boleh melenceng dari buku besarnya. */
    public function test_the_cached_balance_always_equals_the_ledger(): void
    {
        $wallet = $this->ledger->walletFor($this->user);

        $this->ledger->credit($wallet, WalletEntryType::Topup, 100_000);
        $this->ledger->credit($wallet, WalletEntryType::Earning, 250_000);
        $this->ledger->debit($wallet, WalletEntryType::Withdrawal, 90_000);
        $this->ledger->credit($wallet, WalletEntryType::Refund, 40_000);

        $wallet->refresh();

        $this->assertSame(300_000, (int) $wallet->balance);
        $this->assertSame((int) $wallet->balance, $wallet->recomputedBalance());
    }

    public function test_a_debit_beyond_the_balance_is_refused(): void
    {
        $wallet = $this->ledger->walletFor($this->user);
        $this->ledger->credit($wallet, WalletEntryType::Topup, 30_000);

        try {
            $this->ledger->debit($wallet, WalletEntryType::Withdrawal, 30_001);
            $this->fail('debit melebihi saldo seharusnya ditolak');
        } catch (InsufficientBalanceException $e) {
            $this->assertSame('insufficient_balance', $e->errorCode());
            $this->assertSame(
                ['balance' => 30_000, 'requested' => 30_001, 'shortfall' => 1],
                $e->context(),
            );
        }

        // Dan tidak meninggalkan apa pun.
        $this->assertSame(30_000, (int) $wallet->refresh()->balance);
        $this->assertSame(1, $wallet->entries()->count());
    }

    /** Saldo boleh persis nol — batasnya `< 0`, bukan `<= 0`. */
    public function test_a_debit_down_to_exactly_zero_is_allowed(): void
    {
        $wallet = $this->ledger->walletFor($this->user);
        $this->ledger->credit($wallet, WalletEntryType::Topup, 30_000);

        $this->ledger->debit($wallet, WalletEntryType::Withdrawal, 30_000);

        $this->assertSame(0, (int) $wallet->refresh()->balance);
    }

    /**
     * Arah datang dari JENISNYA. Memaksanya lewat method yang salah harus
     * gagal saat itu juga — bukan mengurangi saldo dari baris kode yang
     * terbaca seperti menambah.
     */
    public function test_a_type_cannot_be_recorded_in_the_wrong_direction(): void
    {
        $wallet = $this->ledger->walletFor($this->user);

        $this->expectException(RuntimeException::class);

        $this->ledger->credit($wallet, WalletEntryType::Withdrawal, 10_000);
    }

    public function test_a_zero_or_negative_amount_is_a_programming_error(): void
    {
        $wallet = $this->ledger->walletFor($this->user);

        $this->expectException(RuntimeException::class);

        $this->ledger->credit($wallet, WalletEntryType::Topup, 0);
    }

    /**
     * Idempotensi dijamin basis data.
     *
     * Ini pengaman terakhir di belakang `lockForUpdate()`: kunci hanya berlaku
     * di dalam transaksi, dan jalur tulis yang belum ada belum tentu
     * mengambilnya. Tanpa indeks ini, satu konfirmasi yang terpanggil dua kali
     * menggandakan uang tanpa galat apa pun.
     */
    public function test_one_event_cannot_produce_two_entries_of_the_same_type(): void
    {
        $wallet = $this->ledger->walletFor($this->user);
        $topup = WalletTopup::factory()->create(['user_id' => $this->user->getKey()]);

        $this->ledger->credit($wallet, WalletEntryType::Topup, 50_000, $topup);

        $this->expectException(QueryException::class);

        $this->ledger->credit($wallet, WalletEntryType::Topup, 50_000, $topup);
    }

    /**
     * Tapi DUA jenis berbeda atas kejadian yang sama tetap boleh — satu
     * penarikan yang ditolak memang punya dua baris: tahanannya dan
     * pengembaliannya.
     */
    public function test_the_same_event_may_have_one_entry_per_type(): void
    {
        $wallet = $this->ledger->walletFor($this->user);
        $topup = WalletTopup::factory()->create(['user_id' => $this->user->getKey()]);

        $this->ledger->credit($wallet, WalletEntryType::Topup, 50_000, $topup);
        $this->ledger->debit($wallet, WalletEntryType::Withdrawal, 20_000, $topup);

        $this->assertSame(2, $wallet->entries()->count());
        $this->assertSame(30_000, (int) $wallet->refresh()->balance);
    }

    /** Baris tanpa kejadian (koreksi manual) tidak terkena indeks unique. */
    public function test_entries_without_a_reference_are_not_deduplicated(): void
    {
        $wallet = $this->ledger->walletFor($this->user);

        $this->ledger->credit($wallet, WalletEntryType::AdjustmentCredit, 10_000);
        $this->ledger->credit($wallet, WalletEntryType::AdjustmentCredit, 10_000);

        $this->assertSame(2, $wallet->entries()->count());
        $this->assertSame(20_000, (int) $wallet->refresh()->balance);
    }

    /** `walletFor()` dipanggil dua kali tidak menghasilkan dua dompet. */
    public function test_a_person_has_at_most_one_wallet(): void
    {
        $first = $this->ledger->walletFor($this->user);
        $second = $this->ledger->walletFor($this->user);

        $this->assertTrue($first->is($second));
    }

    /** Jalur BACA tidak boleh membuat baris. */
    public function test_reading_a_wallet_does_not_create_one(): void
    {
        $wallet = $this->user->walletOrNew();

        $this->assertFalse($wallet->exists);
        $this->assertSame(0, (int) $wallet->balance);
        $this->assertSame(0, $this->user->walletBalance());
    }
}
