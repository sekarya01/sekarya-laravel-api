<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Jobs\ShipAxiomBatch;
use App\Logging\Axiom\AxiomClient;
use App\Logging\Axiom\AxiomLogger;
use App\Logging\Axiom\Redactor;
use App\Providers\AxiomServiceProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

/**
 * Jalur kegagalan dan jalur yang jarang dilewati.
 *
 * Observability adalah PENGAMAT, bukan ketergantungan. Sebagian besar test di
 * sini menegaskan satu hal: apa pun yang rusak di dalam pencatatan, request
 * pengguna tetap selesai. Jalur seperti ini justru hanya dipanggil pada saat
 * terburuk, jadi kalau tidak diuji, ia tidak diketahui bekerja.
 */
final class AxiomResilienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('axiom.enabled', true);
        config()->set('axiom.token', 'token-uji');
        config()->set('axiom.dataset', 'ds-uji');
        config()->set('axiom.delivery', 'sync');

        app(AxiomLogger::class)->discard();
    }

    // ── Job pengiriman ──────────────────────────────────────────────────────

    public function test_the_shipping_job_hands_its_batch_to_the_client(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        (new ShipAxiomBatch([['event' => 'axiom.ping']]))->handle(app(AxiomClient::class));

        Http::assertSent(fn ($request): bool => $request->data()[0]['event'] === 'axiom.ping');
    }

    // ── Kegagalan di dalam pencatatan itu sendiri ───────────────────────────

    /**
     * Config yang melempar hanya untuk satu kunci: cara paling langsung untuk
     * meniru "langkah penyaringan meledak" tanpa melonggarkan `final` pada
     * Redactor demi test.
     */
    private function loggerWithBrokenRedactor(): AxiomLogger
    {
        $broken = new class(app('config')->all()) extends ConfigRepository
        {
            public function get($key, $default = null)
            {
                if ($key === 'axiom.limits.max_depth') {
                    throw new RuntimeException('penyaring rusak');
                }

                return parent::get($key, $default);
            }
        };

        return new AxiomLogger(app(AxiomClient::class), new Redactor($broken), app('config'));
    }

    /**
     * Kalau penyaringan gagal, event itu DIBUANG — mengirim yang belum
     * tersaring bukan pilihan.
     */
    public function test_an_event_that_cannot_be_filtered_is_dropped_not_sent_raw(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        $logger = $this->loggerWithBrokenRedactor();
        $logger->event('http.request', ['http' => ['body' => ['password' => 'RahasiaKuat2026']]]);

        $this->assertSame([], $logger->pending());

        $logger->flush();

        Http::assertSent(function ($request): bool {
            $events = $request->data();

            return count($events) === 1
                && $events[0]['event'] === 'axiom.dropped'
                && $events[0]['dropped'] === 1;
        });
    }

    /**
     * Regresi: sebelumnya flush berhenti lebih dulu saat buffer kosong,
     * sehingga kehilangan yang MENYELURUH — setiap event terbuang — tidak
     * pernah dilaporkan ke mana pun. Justru kasus itu yang paling perlu
     * terlihat di Axiom.
     */
    public function test_a_total_loss_is_still_reported(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        $logger = $this->loggerWithBrokenRedactor();

        for ($i = 0; $i < 3; $i++) {
            $logger->event('http.request');
        }

        $logger->flush();

        Http::assertSent(fn ($request): bool => $request->data()[0]['dropped'] === 3);
    }

    /** Queue tidak terjangkau (mis. tabel `jobs` mati) tidak boleh melempar. */
    public function test_a_broken_delivery_path_never_escapes_the_flush(): void
    {
        config()->set('axiom.delivery', 'queue');
        config()->set('queue.default', 'koneksi-yang-tidak-ada');

        $logger = app(AxiomLogger::class);
        $logger->event('http.request');

        $logger->flush();

        $this->assertSame([], $logger->pending());
    }

    // ── Penangan galat fatal ────────────────────────────────────────────────

    private function provider(): AxiomServiceProvider
    {
        return new AxiomServiceProvider($this->app);
    }

    /**
     * Nilai utama hook ini bukan pesan galatnya — Laravel biasanya menangkap
     * itu — tapi FLUSH-nya: tanpa hook ini, proses yang mati fatal membawa
     * hilang seluruh event request tersebut.
     */
    public function test_a_fatal_error_is_recorded_and_the_buffer_is_still_flushed(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        app(AxiomLogger::class)->event('http.request', ['http' => ['path' => '/api/v1/tasks']]);

        $this->provider()->handleShutdown([
            'type' => E_ERROR,
            'message' => 'Allowed memory size exhausted',
            'file' => base_path('app/Actions/Task/ListTasksAction.php'),
            'line' => 88,
        ]);

        Http::assertSent(function ($request): bool {
            $names = array_column($request->data(), 'event');

            // Event yang sudah tertumpuk sebelum runtuh ikut terselamatkan —
            // itulah yang menjelaskan apa yang sedang dikerjakan.
            return in_array('http.request', $names, true)
                && in_array('error.fatal', $names, true);
        });
    }

    public function test_a_non_fatal_shutdown_only_flushes(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        app(AxiomLogger::class)->event('http.request');

        $this->provider()->handleShutdown([
            'type' => E_WARNING,
            'message' => 'Undefined variable',
            'file' => 'x.php',
            'line' => 1,
        ]);

        Http::assertSent(fn ($request): bool => array_column($request->data(), 'event') === ['http.request']);
    }

    public function test_a_clean_shutdown_with_an_empty_buffer_sends_nothing(): void
    {
        Http::fake();

        $this->provider()->handleShutdown(null);

        Http::assertNothingSent();
    }

    /**
     * Berjalan setelah proses runtuh: tidak ada tempat untuk melempar.
     *
     * Container sengaja dibuat gagal meresolusi AxiomLogger — kegagalan yang
     * terjadi DI LUAR try/catch milik flush(), sehingga yang diuji benar-benar
     * penjaga di handleShutdown() dan bukan penjaga di lapisan bawahnya.
     */
    public function test_the_shutdown_handler_swallows_its_own_failures(): void
    {
        $this->app->bind(AxiomLogger::class, static fn () => throw new RuntimeException('container runtuh'));

        $this->provider()->handleShutdown(null);

        $this->assertTrue(true, 'tidak melempar');
    }

    // ── Gerbang per-kelompok pada provider ──────────────────────────────────

    /**
     * Setiap kelompok bisa dimatikan sendiri, dan mematikannya harus berarti
     * listener-nya TIDAK terpasang — bukan terpasang lalu diam. Untuk
     * `QueryExecuted` bedanya nyata: ia dipanggil pada setiap kueri.
     */
    public function test_no_listener_is_attached_for_a_capture_group_that_is_off(): void
    {
        $fresh = $this->createApplication();
        $fresh['config']->set('axiom.enabled', true);

        foreach (['auth', 'jobs', 'slow_queries', 'outbound_http', 'mail', 'console'] as $group) {
            $fresh['config']->set('axiom.capture.'.$group, false);
        }

        $watched = [
            Login::class,
            JobFailed::class,
            QueryExecuted::class,
            ResponseReceived::class,
            MessageSending::class,
            CommandFinished::class,
        ];

        $before = array_map(
            static fn (string $e): int => count($fresh['events']->getListeners($e)),
            $watched,
        );

        $fresh->register(AxiomServiceProvider::class, true);

        $after = array_map(
            static fn (string $e): int => count($fresh['events']->getListeners($e)),
            $watched,
        );

        $this->assertSame($before, $after, 'kelompok yang mati tidak boleh menambah listener apa pun');
    }

    // ── Listener yang belum terlewati ───────────────────────────────────────

    private function bootListeners(): void
    {
        $this->app->register(AxiomServiceProvider::class, true);
        app(AxiomLogger::class)->discard();
    }

    private function job(string $name): Job
    {
        $job = Mockery::mock(Job::class);
        $job->allows('resolveName')->andReturns($name);
        $job->allows('getQueue')->andReturns('default');
        $job->allows('attempts')->andReturns(1);
        $job->allows('uuid')->andReturns('uuid-1');

        // ContextServiceProvider bawaan Laravel membacanya pada JobProcessing.
        $job->allows('payload')->andReturns([]);

        return $job;
    }

    /** @return array<int, string> */
    private function pendingNames(): array
    {
        return array_column(app(AxiomLogger::class)->pending(), 'event');
    }

    public function test_queueing_and_starting_a_job_are_recorded(): void
    {
        $this->bootListeners();

        event(new JobQueued('database', 'default', 'id-1', new ShipAxiomBatch([]), 'payload', null));
        $this->assertSame([], $this->pendingNames(), 'job pengirim Axiom sendiri tidak boleh dicatat');

        event(new JobQueued('database', 'high', 'id-2', new \stdClass, 'payload', 30));

        $event = app(AxiomLogger::class)->pending()[0];

        $this->assertSame('job.queued', $event['event']);
        $this->assertSame('stdClass', $event['job']['name']);
        $this->assertSame('high', $event['job']['queue']);
        $this->assertSame(30, $event['job']['delay']);

        app(AxiomLogger::class)->discard();

        event(new JobProcessing('database', $this->job('App\Jobs\SendMail')));
        $this->assertSame(['job.processing'], $this->pendingNames());
    }

    public function test_the_axiom_shipping_job_is_skipped_on_every_job_event(): void
    {
        $this->bootListeners();

        $own = $this->job('App\Jobs\ShipAxiomBatch');

        event(new JobProcessing('database', $own));
        event(new JobFailed('database', $own, new RuntimeException('x')));

        $this->assertSame([], $this->pendingNames());
    }

    /**
     * Gateway pembayaran tidak menjawab — salah satu galat produksi yang paling
     * membingungkan kalau tidak tercatat, karena tidak meninggalkan jejak apa
     * pun di sisi kita.
     */
    public function test_an_outbound_connection_failure_is_recorded(): void
    {
        $this->bootListeners();

        // `Http::failedConnection()`, bukan callback yang melempar: melempar
        // dari dalam callback melewati jalur kegagalan koneksi Laravel, jadi
        // ConnectionFailed tidak pernah dipancarkan dan test-nya lulus palsu.
        Http::fake(['gateway.test/*' => Http::failedConnection('cURL error 28: timeout')]);

        try {
            Http::get('https://gateway.test/v1/charge?api_key=rahasia-kunci');
        } catch (ConnectionException) {
            // yang diuji adalah eventnya, bukan lemparannya
        }

        $event = app(AxiomLogger::class)->pending()[0];

        $this->assertSame('outbound.connection_failed', $event['event']);
        $this->assertSame('error', $event['level']);
        $this->assertSame('gateway.test', $event['outbound']['host']);
        $this->assertSame('/v1/charge', $event['outbound']['path']);
        $this->assertStringContainsString('timeout', $event['error']['message']);
        $this->assertStringNotContainsString('rahasia-kunci', (string) json_encode($event));
    }

    public function test_a_successful_command_adds_nothing(): void
    {
        $this->bootListeners();

        event(new CommandFinished('sekarya:axiom', new ArrayInput([]), new NullOutput, 0));

        $this->assertSame([], $this->pendingNames());
    }

    // ── Sudut Redactor yang belum terlewati ─────────────────────────────────

    public function test_the_email_domain_can_be_withheld_too(): void
    {
        config()->set('axiom.privacy.keep_email_domain', false);

        $out = app(Redactor::class)->email('budi@contoh.test');

        $this->assertArrayNotHasKey('domain', $out);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $out['sha']);
    }

    public function test_an_identity_key_holding_an_unexpected_type_never_leaks_it(): void
    {
        $out = app(Redactor::class)->payload([
            'email' => ['budi@contoh.test', 'siti@contoh.test'],
            'phone' => true,
            'name' => null,
            'full_name' => '',
        ]);

        $json = (string) json_encode($out);

        $this->assertStringNotContainsString('budi@contoh.test', $json);
        $this->assertSame('[items:2]', $out['email']);
        $this->assertSame('[redacted]', $out['phone']);
        $this->assertNull($out['name']);
        $this->assertNull($out['full_name_sha']);
    }

    public function test_summarized_keys_report_shape_for_every_type(): void
    {
        $out = app(Redactor::class)->payload([
            'bio' => true,
            'note' => 7,
            'comment' => 1.5,
            'reason' => new \stdClass,
        ]);

        $this->assertSame('[bool]', $out['bio']);
        $this->assertSame('[num]', $out['note']);
        $this->assertSame('[num]', $out['comment']);
        $this->assertSame('[redacted]', $out['reason']);
    }

    public function test_a_resource_value_is_reduced_to_its_type(): void
    {
        $handle = fopen('php://memory', 'r');

        $out = app(Redactor::class)->payload(['stream' => $handle]);

        fclose($handle);

        $this->assertStringContainsString('resource', $out['stream']);
    }
}
