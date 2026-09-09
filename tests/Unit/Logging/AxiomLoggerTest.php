<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Jobs\ShipAxiomBatch;
use App\Logging\Axiom\AxiomLogger;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Penampung dan pembentuk event.
 *
 * Dua hal yang paling penting diuji di sini dan alasannya:
 *
 *  - `enabled=false` harus berarti NOL data keluar. Ini gerbang yang membuat
 *    seluruh integrasi aman terpasang di semua lingkungan.
 *  - `request_id` harus sama untuk seluruh event dalam satu proses. Itu
 *    satu-satunya hal yang mengubah tumpukan baris log menjadi jejak sebuah
 *    request yang bisa dibaca.
 */
final class AxiomLoggerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('axiom.enabled', true);
        config()->set('axiom.token', 'token-uji');
        config()->set('axiom.dataset', 'ds-uji');
        config()->set('axiom.delivery', 'sync');
        config()->set('axiom.service', 'sekarya-api');

        app(AxiomLogger::class)->discard();
    }

    private function logger(): AxiomLogger
    {
        return app(AxiomLogger::class);
    }

    // ── Gerbang enabled ─────────────────────────────────────────────────────

    public function test_nothing_is_buffered_while_disabled(): void
    {
        config()->set('axiom.enabled', false);
        Http::fake();

        $this->logger()->event('http.request', ['http' => ['status' => 200]]);
        $this->logger()->flush();

        $this->assertSame([], $this->logger()->pending());
        Http::assertNothingSent();
    }

    public function test_a_capture_group_can_be_turned_off_on_its_own(): void
    {
        config()->set('axiom.capture.jobs', false);
        config()->set('axiom.capture.http', true);

        $this->assertFalse($this->logger()->capturing('jobs'));
        $this->assertTrue($this->logger()->capturing('http'));
    }

    public function test_capturing_is_false_for_every_group_when_axiom_is_disabled(): void
    {
        config()->set('axiom.enabled', false);
        config()->set('axiom.capture.http', true);

        $this->assertFalse($this->logger()->capturing('http'));
    }

    // ── Envelope ────────────────────────────────────────────────────────────

    public function test_every_event_carries_the_machine_and_service_envelope(): void
    {
        $this->logger()->event('http.request', ['http' => ['status' => 204]]);

        $event = $this->logger()->pending()[0];

        $this->assertSame('sekarya-api', $event['service']);
        $this->assertSame('testing', $event['env']);
        $this->assertSame('http', $event['kind'], 'kind diturunkan dari awalan nama event');
        $this->assertSame('http.request', $event['event']);
        $this->assertSame('info', $event['level']);
        $this->assertSame(204, $event['http']['status']);
        $this->assertNotEmpty($event['host']);

        // Format waktu Axiom: RFC3339 dengan mikrodetik.
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}(Z|[+\-]\d{2}:\d{2})$/',
            $event['_time'],
        );
    }

    /** `release` menjawab "versi mana yang error" — tanpa isi, ia tidak dikirim. */
    public function test_release_is_included_only_when_set(): void
    {
        config()->set('axiom.release', null);
        $this->logger()->event('log.record');

        $this->assertArrayNotHasKey('release', $this->logger()->pending()[0]);

        config()->set('axiom.release', 'a1b2c3d');
        $this->logger()->discard();
        $this->logger()->event('log.record');

        $this->assertSame('a1b2c3d', $this->logger()->pending()[0]['release']);
    }

    public function test_the_request_id_is_shared_by_every_event_in_the_process(): void
    {
        $this->logger()->event('http.request');
        $this->logger()->event('db.slow_query');
        $this->logger()->event('log.record');

        $ids = array_column($this->logger()->pending(), 'request_id');

        $this->assertCount(3, $ids);
        $this->assertCount(1, array_unique($ids));
        $this->assertSame($this->logger()->requestId(), $ids[0]);
    }

    /** Jejak harus tetap menyatu melewati beberapa layanan. */
    public function test_an_incoming_request_id_can_be_adopted(): void
    {
        $this->logger()->setRequestId('trace-dari-gateway');
        $this->logger()->event('http.request');

        $this->assertSame('trace-dari-gateway', $this->logger()->pending()[0]['request_id']);
    }

    public function test_context_is_attached_to_every_later_event(): void
    {
        $this->logger()->withContext(['user_id' => '01JBQ7XK2M3N4P5Q6R7S8T9V0W']);
        $this->logger()->event('http.request');

        $this->assertSame('01JBQ7XK2M3N4P5Q6R7S8T9V0W', $this->logger()->pending()[0]['user_id']);
    }

    public function test_null_context_values_are_dropped_rather_than_sent_as_empty_columns(): void
    {
        $this->logger()->withContext(['user_id' => null]);
        $this->logger()->event('http.request');

        $this->assertArrayNotHasKey('user_id', $this->logger()->pending()[0]);
    }

    /** Jaring terakhir: lihat RedactorTest untuk aturannya. */
    public function test_the_envelope_is_scrubbed_even_if_a_caller_forgot(): void
    {
        $this->logger()->event('log.record', ['log' => ['message' => 'gagal untuk budi@contoh.test']]);

        $this->assertStringNotContainsString(
            'budi@contoh.test',
            (string) json_encode($this->logger()->pending()),
        );
    }

    /**
     * Waktu mulai disimpan di sini, bukan di middleware, karena Laravel
     * membuat instance middleware baru untuk `terminate()`. Di luar siklus
     * HTTP — console, queue — tidak ada yang menandainya, dan itu harus
     * menghasilkan "tidak tahu", bukan angka sejak epoch.
     */
    public function test_elapsed_time_is_unknown_until_a_request_marks_its_start(): void
    {
        $this->assertNull($this->logger()->elapsedMs());

        $this->logger()->markStart(microtime(true) - 0.25);

        $elapsed = $this->logger()->elapsedMs();

        $this->assertNotNull($elapsed);
        $this->assertGreaterThanOrEqual(250, $elapsed);
        $this->assertLessThan(60_000, $elapsed);
    }

    // ── Pengiriman ──────────────────────────────────────────────────────────

    public function test_flush_sends_one_batch_and_empties_the_buffer(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        $this->logger()->event('http.request');
        $this->logger()->event('db.slow_query');
        $this->logger()->flush();

        $this->assertSame([], $this->logger()->pending());
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r): bool => count($r->data()) === 2);
    }

    /**
     * Buffer dikosongkan SEBELUM pengiriman: kalau tidak, sebuah kegagalan
     * transport membuat event yang sama terkirim ulang dan menggandakan diri.
     */
    public function test_a_failed_send_does_not_leave_events_to_be_sent_twice(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 500)]);

        $this->logger()->event('http.request');
        $this->logger()->flush();
        $this->logger()->flush();

        Http::assertSentCount(1);
    }

    public function test_flushing_an_empty_buffer_touches_no_network(): void
    {
        Http::fake();

        $this->logger()->flush();

        Http::assertNothingSent();
    }

    public function test_queue_delivery_hands_the_batch_to_a_worker_instead(): void
    {
        config()->set('axiom.delivery', 'queue');
        config()->set('axiom.queue', 'observability');
        Queue::fake();
        Http::fake();

        $this->logger()->event('http.request');
        $this->logger()->flush();

        Http::assertNothingSent();
        Queue::assertPushedOn('observability', ShipAxiomBatch::class);
    }

    // ── Batas & ketahanan ───────────────────────────────────────────────────

    /**
     * Membuang event lebih baik daripada menghabiskan memori proses — tapi
     * lubangnya harus TERLIHAT di Axiom, bukan hilang tanpa jejak.
     */
    public function test_the_buffer_is_capped_and_the_loss_is_reported(): void
    {
        config()->set('axiom.max_batch', 3);
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        for ($i = 0; $i < 10; $i++) {
            $this->logger()->event('http.request');
        }

        $this->assertCount(3, $this->logger()->pending());

        $this->logger()->flush();

        Http::assertSent(function (Request $request): bool {
            $events = $request->data();

            return count($events) === 4
                && $events[3]['event'] === 'axiom.dropped'
                && $events[3]['dropped'] === 7;
        });
    }

    public function test_discard_throws_the_buffer_away_without_sending(): void
    {
        Http::fake();

        $this->logger()->event('http.request');
        $this->logger()->discard();
        $this->logger()->flush();

        Http::assertNothingSent();
    }

    /**
     * Re-entrancy: pengiriman memanggil HTTP client dan LogManager, dan
     * keduanya memancarkan event yang kelas ini juga dengarkan. Tanpa penjaga,
     * satu flush memicu flush berikutnya tanpa henti.
     */
    public function test_an_event_emitted_during_a_flush_is_ignored(): void
    {
        $logger = $this->logger();

        Http::fake(function () use ($logger) {
            // Meniru listener yang berjalan di tengah pengiriman.
            $logger->event('outbound.response', ['outbound' => ['host' => 'api.axiom.co']]);

            return Http::response('', 200);
        });

        $logger->event('http.request');
        $logger->flush();

        $this->assertSame([], $logger->pending(), 'event dari dalam flush tidak boleh menumpuk lagi');
        Http::assertSentCount(1);
    }
}
