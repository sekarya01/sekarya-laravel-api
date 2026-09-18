<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletWithdrawal;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Kepemilikan tidak boleh bergantung pada TIPE yang dikembalikan driver.
 *
 * Seluruh Policy membandingkan `$user->getKey()` dengan kolom asing memakai
 * `===`. Laravel meng-cast primary key model sendiri ke int, tapi TIDAK
 * meng-cast kolom asing — nilainya apa adanya dari driver. Bila driver
 * mengembalikan string, `int === string` bernilai false dan pemilik asli
 * ditolak 403.
 *
 * Gejalanya menyesatkan karena tidak menyeluruh: kueri yang membandingkan
 * kolom yang sama DI SQL tetap benar (MySQL menyamakan tipe), jadi daftar
 * "tugas saya" tetap berisi tugasnya sementara aksi atas tugas itu ditolak.
 * Itu yang terjadi di produksi pada 2026-09-18.
 *
 * Test ini memaksa kolomnya berisi string, lalu menuntut jawabannya tetap
 * sama. Cast di model yang membuatnya lulus.
 */
final class OwnerAuthorizationTypeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  class-string  $model
     * @return array<string, array{class-string, string}>
     */
    public static function ownerColumns(): array
    {
        return [
            'task.poster_id' => [Task::class, 'poster_id'],
            'bid.bidder_id' => [Bid::class, 'bidder_id'],
            'activity.worker_id' => [Activity::class, 'worker_id'],
            'wallet_topup.user_id' => [WalletTopup::class, 'user_id'],
            'wallet_withdrawal.user_id' => [WalletWithdrawal::class, 'user_id'],
        ];
    }

    #[DataProvider('ownerColumns')]
    public function test_owner_columns_stay_integers_even_when_the_driver_hands_back_strings(
        string $model,
        string $column,
    ): void {
        $instance = new $model;
        $instance->setRawAttributes([$column => '12345'], true);

        $this->assertSame(
            12345,
            $instance->{$column},
            sprintf(
                '%s::$%s harus int. Tanpa cast, nilai string dari driver membuat '
                ."Policy menolak pemilik aslinya dengan 403.\n",
                $model,
                $column,
            ),
        );
    }

    /**
     * Dan penjaganya di Policy itu sendiri.
     *
     * Tidak lewat HTTP: kolom `bigint` di MySQL tidak bisa diisi string, jadi
     * satu-satunya tempat perbedaan tipe ini bisa muncul adalah batas antara
     * driver dan model — persis yang ditiru di sini.
     */
    public function test_the_policy_still_recognises_the_poster_when_the_column_is_a_string(): void
    {
        $poster = User::factory()->make();
        $poster->id = 12345;

        $task = new Task;
        $task->setRawAttributes(['id' => 9, 'poster_id' => '12345'], true);

        $policy = new TaskPolicy;

        $this->assertTrue($policy->update($poster, $task));
        $this->assertTrue($policy->manageBids($poster, $task));
    }
}
