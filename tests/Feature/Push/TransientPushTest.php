<?php

declare(strict_types=1);

namespace Tests\Feature\Push;

use App\Jobs\SendPushNotification;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Push chat harus realtime: dikirim sesudah respons, BUKAN lewat antrean
 * (di hosting bersama antrean baru diproses cron tiap menit), dan tetap
 * hanya sesudah transaksi pemanggilnya commit.
 */
final class TransientPushTest extends TestCase
{
    public function test_chat_push_is_sent_after_the_response_not_queued(): void
    {
        Queue::fake();
        Bus::fake([SendPushNotification::class]);

        app(PushDispatcher::class)->sendTransient(1, $this->message());

        Queue::assertNothingPushed();
        Bus::assertDispatchedAfterResponse(SendPushNotification::class);
    }

    public function test_chat_push_waits_for_commit_and_is_dropped_on_rollback(): void
    {
        Bus::fake([SendPushNotification::class]);

        try {
            DB::transaction(function (): void {
                app(PushDispatcher::class)->sendTransient(1, $this->message());
                Bus::assertNotDispatchedAfterResponse(SendPushNotification::class);

                throw new RuntimeException('batal');
            });
        } catch (RuntimeException) {
            // Transaksi sengaja dibatalkan.
        }

        Bus::assertNotDispatchedAfterResponse(SendPushNotification::class);
    }

    private function message(): PushMessage
    {
        return new PushMessage('Rina', 'Besok jam 8 bisa?', ['type' => 'chat_message']);
    }
}
