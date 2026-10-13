<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Wallet;

use App\Actions\Wallet\RequestTopupAction;
use App\Data\Wallet\CreateTopupData;
use App\Enums\WalletTopupStatus;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Nominal transfer isi ulang yang menunggu tidak boleh kembar — pengelola
 * mencocokkan mutasi bank dengan nominal itu. Dijaga unique
 * `awaiting_transfer_lock`; Action memilih kode lain saat bertabrakan.
 */
final class RequestTopupActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_database_rejects_two_awaiting_topups_with_the_same_transfer_amount(): void
    {
        WalletTopup::factory()->create(['amount' => 100_000, 'unique_code' => 5, 'transfer_amount' => 100_005]);

        $this->expectException(UniqueConstraintViolationException::class);

        WalletTopup::factory()->create(['amount' => 100_000, 'unique_code' => 5, 'transfer_amount' => 100_005]);
    }

    public function test_settled_and_legacy_topups_may_share_a_transfer_amount(): void
    {
        WalletTopup::factory()->confirmed()->create(['amount' => 100_000, 'unique_code' => 5, 'transfer_amount' => 100_005]);
        WalletTopup::factory()->create(['amount' => 100_000, 'unique_code' => 5, 'transfer_amount' => 100_005]);
        // Baris sebelum kode unik ada: kode 0, nominal = jumlah.
        WalletTopup::factory()->count(2)->create(['amount' => 50_000, 'unique_code' => 0, 'transfer_amount' => 50_000]);

        $this->assertSame(4, WalletTopup::query()->count());
    }

    public function test_a_colliding_code_is_picked_again_instead_of_failing(): void
    {
        $amount = 100_000;
        $other = User::factory()->create();
        $now = now();

        // Semua kode terpakai kecuali 500, dan nominal 100.500 sudah dipegang
        // baris lain (kode 7, jumlah 100.493) — tabrakan yang dalam produksi
        // datang dari permintaan bersamaan. Pilihan pertama PASTI 500.
        $rows = [];
        foreach (range(1, 999) as $code) {
            if ($code === 500) {
                continue;
            }
            $base = $code === 7 ? $amount + 500 - 7 : 10_000_000 + $code * 1_000;
            $rows[] = [
                'ulid' => (string) Str::ulid(),
                'user_id' => $other->getKey(),
                'amount' => $base,
                'unique_code' => $code,
                'transfer_amount' => $base + $code,
                'status' => WalletTopupStatus::AwaitingConfirmation->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('wallet_topups')->insert($chunk);
        }

        $topup = app(RequestTopupAction::class)->handle(User::factory()->create(), new CreateTopupData($amount));

        $this->assertNotSame($amount + 500, (int) $topup->transfer_amount);
        $this->assertSame($amount + (int) $topup->unique_code, (int) $topup->transfer_amount);
        $this->assertSame(WalletTopupStatus::AwaitingConfirmation, $topup->status);
    }
}
