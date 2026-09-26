<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Activity;
use App\Support\WorkerPayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Biaya layanan (G6): dipotong dari upah pekerja, dua baris buku besar.
 */
final class ServiceFeeTest extends TestCase
{
    use RefreshDatabase;

    private function payableActivity(): array
    {
        $worker = $this->activeUser();
        $activity = Activity::factory()->create([
            'worker_id' => $worker->getKey(),
            'agreed_amount' => 100_000,
        ]);

        return [$worker, $activity];
    }

    public function test_the_fee_is_deducted_and_both_entries_are_written(): void
    {
        config()->set('sekarya.fees.service_percent', 10);

        [$worker, $activity] = $this->payableActivity();

        app(WorkerPayout::class)->pay($activity, 'Upah uji');

        $wallet = $worker->fresh()->wallet;

        $this->assertSame(90_000, (int) $wallet->balance);
        $this->assertDatabaseHas('wallet_entries', [
            'wallet_id' => $wallet->getKey(),
            'type' => 'earning',
            'amount' => 100_000,
        ]);
        $this->assertDatabaseHas('wallet_entries', [
            'wallet_id' => $wallet->getKey(),
            'type' => 'fee',
            'amount' => 10_000,
        ]);

        // Sisi platform: potongan tidak hilang dari neraca (G6).
        $this->assertDatabaseHas('platform_fee_entries', [
            'activity_id' => $activity->getKey(),
            'worker_id' => $worker->getKey(),
            'gross_amount' => 100_000,
            'fee_amount' => 10_000,
            'percent_bp' => 1000,
        ]);
    }

    public function test_no_fee_is_charged_when_the_percent_is_zero(): void
    {
        config()->set('sekarya.fees.service_percent', 0);

        [$worker, $activity] = $this->payableActivity();

        app(WorkerPayout::class)->pay($activity, 'Upah uji');

        $wallet = $worker->fresh()->wallet;

        $this->assertSame(100_000, (int) $wallet->balance);
        $this->assertDatabaseMissing('wallet_entries', [
            'wallet_id' => $wallet->getKey(),
            'type' => 'fee',
        ]);
    }
}
