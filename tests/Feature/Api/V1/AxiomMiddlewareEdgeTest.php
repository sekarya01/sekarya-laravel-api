<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Logging\Axiom\AxiomLogger;
use App\Logging\Axiom\Redactor;
use App\Providers\AxiomServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Jalur tepi middleware pencatat.
 *
 * Yang diuji di sini bukan jalur bahagia — itu ada di AxiomObservabilityTest —
 * melainkan keputusan-keputusan yang menentukan apakah pencatatan bisa
 * merugikan: saklar per-kelompok, sampling, payload raksasa, respons yang bukan
 * JSON, dan yang paling penting, pencatat yang rusak.
 */
final class AxiomMiddlewareEdgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('axiom.enabled', true);
        config()->set('axiom.token', 'token-uji');
        config()->set('axiom.dataset', 'ds-uji');

        Http::fake([
            'api.axiom.co/*' => Http::response('', 200),
            '*' => Http::response('', 200),
        ]);

        $this->app->register(AxiomServiceProvider::class, true);

        app(AxiomLogger::class)->discard();
    }

    /** @return array<int, array<string, mixed>> */
    private function sentEvents(): array
    {
        $events = [];

        foreach (Http::recorded() as [$request]) {
            if (str_contains($request->url(), 'axiom.co')) {
                $events = [...$events, ...$request->data()];
            }
        }

        return $events;
    }

    /** @return array<int, string> */
    private function sentNames(): array
    {
        return array_column($this->sentEvents(), 'event');
    }

    /** @return array<string, mixed> */
    private function event(string $name): array
    {
        foreach ($this->sentEvents() as $event) {
            if ($event['event'] === $name) {
                return $event;
            }
        }

        $this->fail(sprintf('event "%s" tidak terkirim. Ada: %s', $name, implode(', ', $this->sentNames()) ?: '(tidak ada)'));
    }

    // ── Pencatat yang rusak ─────────────────────────────────────────────────

    /**
     * Test terpenting di kelas ini.
     *
     * Observability adalah PENGAMAT, bukan ketergantungan. Kalau penyaringan
     * meledak di tengah jalan, pengguna tetap harus mendapat responsnya —
     * bukan 500 karena sistem log-nya sendiri yang rusak.
     */
    public function test_a_broken_logger_never_turns_into_a_failed_request(): void
    {
        $broken = new class(app('config')->all()) extends ConfigRepository
        {
            public function get($key, $default = null)
            {
                if ($key === 'axiom.allowed_headers') {
                    throw new RuntimeException('pencatat rusak');
                }

                return parent::get($key, $default);
            }
        };

        $this->app->instance(Redactor::class, new Redactor($broken));

        $this->getJson(route('v1.me.show'))->assertUnauthorized();

        // Kegagalan itu hanya berarti kehilangan satu event, bukan insiden.
        $this->assertNotContains('http.request', $this->sentNames());
    }

    // ── Saklar per-kelompok ─────────────────────────────────────────────────

    public function test_http_capture_can_be_turned_off_on_its_own(): void
    {
        config()->set('axiom.capture.http', false);

        $this->getJson(route('v1.me.show'))->assertUnauthorized();

        $this->assertNotContains('http.request', $this->sentNames());
    }

    public function test_security_capture_can_be_turned_off_while_http_stays_on(): void
    {
        config()->set('axiom.capture.security', false);

        $this->getJson(route('v1.me.show'))->assertUnauthorized();

        $this->assertContains('http.request', $this->sentNames());
        $this->assertNotContains('security.unauthenticated', $this->sentNames());
    }

    public function test_auth_capture_can_be_turned_off_while_http_stays_on(): void
    {
        config()->set('axiom.capture.auth', false);
        $this->activeUser(['email' => 'budi@contoh.test', 'password' => bcrypt('RahasiaKuat2026')]);

        $this->postJson(route('v1.auth.login'), [
            'email' => 'budi@contoh.test',
            'password' => 'RahasiaKuat2026',
        ])->assertOk();

        $this->assertContains('http.request', $this->sentNames());
        $this->assertNotContains('auth.login', $this->sentNames());
    }

    /**
     * Rute auth yang hanya punya nama event untuk sukses tidak memancarkan
     * apa pun saat gagal — logout yang ditolak 401 sudah tercatat sebagai
     * `security.unauthenticated`, dan `auth.logout_failed` tidak menambah
     * apa-apa selain kebisingan.
     */
    public function test_an_auth_route_without_a_failure_event_stays_quiet_on_failure(): void
    {
        $this->postJson(route('v1.auth.logout'))->assertUnauthorized();

        $names = $this->sentNames();

        $this->assertContains('security.unauthenticated', $names);
        $this->assertSame([], array_values(array_filter(
            $names,
            static fn (string $n): bool => str_starts_with($n, 'auth.'),
        )));
    }

    // ── Sampling ────────────────────────────────────────────────────────────

    /**
     * Sampling boleh membuang trafik sukses yang membosankan, TAPI tidak boleh
     * menyentuh yang gagal — justru yang jarang itulah yang di-debug.
     */
    public function test_sampling_can_drop_successful_traffic_but_never_failures(): void
    {
        config()->set('axiom.sampling.success', 0.0);

        $user = $this->activeUser();
        $this->asUser($user)->getJson(route('v1.me.show'))->assertOk();

        $this->assertSame([], $this->sentEvents(), 'sukses dengan sampling 0 tidak dikirim');

        $this->app['auth']->forgetGuards();

        // Token diganti yang tidak sah, bukan sekadar forgetGuards: header
        // Authorization menempel pada instance test dan akan terbawa.
        $this->withHeaders(['Authorization' => 'Bearer token-tidak-sah'])
            ->getJson(route('v1.me.show'))
            ->assertUnauthorized();

        $this->assertContains('http.request', $this->sentNames(), 'kegagalan selalu dikirim apa pun sampling-nya');
    }

    /**
     * Regresi: Laravel membuat instance middleware BARU untuk `terminate()`,
     * jadi waktu mulai yang disimpan sebagai properti instance hilang dan
     * durasi terhitung sejak epoch — miliaran milidetik. Akibat lanjutannya
     * jauh lebih berbahaya daripada angka yang salah: setiap request lolos
     * ambang "lambat", dan karena request lambat tidak pernah di-sample,
     * sampling berhenti bekerja tanpa satu pun tanda.
     */
    public function test_the_recorded_duration_is_a_real_request_duration(): void
    {
        $this->getJson(route('v1.me.show'))->assertUnauthorized();

        $event = $this->event('http.request');

        $this->assertIsInt($event['http']['duration_ms']);
        $this->assertLessThan(
            60_000,
            $event['http']['duration_ms'],
            'durasi di atas satu menit berarti waktu mulai tidak selamat sampai terminate()',
        );
        $this->assertArrayNotHasKey('slow', $event['http']);
    }

    // ── Payload ─────────────────────────────────────────────────────────────

    public function test_request_payload_can_be_withheld_entirely(): void
    {
        config()->set('axiom.privacy.request_payload', 'none');

        $this->getJson(route('v1.tasks.index').'?q=lampu&radius_km=5')->assertUnauthorized();

        $this->assertArrayNotHasKey('query', $this->event('http.request')['http']);
    }

    /**
     * Body raksasa hampir selalu berarti unggahan atau impor massal. Ukurannya
     * cukup untuk men-debug; isinya tidak sepadan risikonya.
     */
    public function test_a_huge_body_is_reduced_to_its_size(): void
    {
        $this->postJson(route('v1.auth.register'), [
            'name' => 'Budi',
            'bio' => str_repeat('x', 6000),
        ])->assertUnprocessable();

        $body = $this->event('http.request')['http']['body'];

        $this->assertIsString($body);
        $this->assertMatchesRegularExpression('/^\[bytes:\d+\]$/', $body);
    }

    /**
     * Sampling pecahan: yang diuji adalah keputusannya benar-benar dijalankan
     * tanpa melempar. Hasilnya acak, jadi tidak ada assertion pada event —
     * assertion yang bergantung pada `mt_rand()` adalah test yang flaky.
     */
    public function test_a_fractional_sampling_rate_is_evaluated_without_error(): void
    {
        config()->set('axiom.sampling.success', 0.5);

        $this->asUser($this->activeUser())->getJson(route('v1.me.show'))->assertOk();
    }

    // ── Otorisasi ───────────────────────────────────────────────────────────

    public function test_an_authorization_denial_is_recorded_as_a_security_event(): void
    {
        Route::middleware('api')->get('/api/v1/uji-403', fn () => abort(403, 'bukan milikmu'));

        $this->get('/api/v1/uji-403')->assertForbidden();

        $event = $this->event('security.forbidden');

        $this->assertSame(403, $event['http']['status']);
        $this->assertSame('notice', $event['level']);
    }

    // ── Unggahan berkas ─────────────────────────────────────────────────────

    /**
     * Nama berkas adalah PII di dalam metadata: "KTP_Budi_Prasetyo.jpg"
     * menyebut orangnya, dan berkas verifikasi di aplikasi ini persis
     * berbentuk begitu. Yang dibutuhkan saat men-debug unggahan gagal hanyalah
     * ukuran dan tipe MIME.
     */
    public function test_an_upload_records_size_and_type_but_never_the_filename(): void
    {
        Route::middleware('api')->post('/api/v1/uji-unggah', fn () => response()->json(['ok' => true]));

        $this->post(
            '/api/v1/uji-unggah',
            ['id_card' => UploadedFile::fake()->image('KTP_Budi_Prasetyo_3271012345678901.jpg')],
            ['Accept' => 'application/json'],
        )->assertOk();

        $event = $this->event('http.request');
        $file = $event['http']['files']['id_card'];

        $this->assertIsInt($file['bytes']);
        $this->assertSame('image/jpeg', $file['mime']);
        $this->assertTrue($file['valid']);

        $json = (string) json_encode($event);

        $this->assertStringNotContainsString('KTP_Budi_Prasetyo', $json);
        $this->assertStringNotContainsString('3271012345678901', $json);
    }

    public function test_a_nested_upload_field_is_skipped_rather_than_guessed_at(): void
    {
        Route::middleware('api')->post('/api/v1/uji-unggah-bersarang', fn () => response()->json(['ok' => true]));

        $this->post(
            '/api/v1/uji-unggah-bersarang',
            ['dokumen' => ['ktp' => ['depan' => UploadedFile::fake()->image('a.jpg')]]],
            ['Accept' => 'application/json'],
        )->assertOk();

        $this->assertArrayNotHasKey('files', $this->event('http.request')['http']);
    }

    // ── Bentuk respons yang tidak terduga ───────────────────────────────────

    public function test_a_non_json_error_response_is_logged_without_an_error_body(): void
    {
        Route::middleware('api')->get('/api/v1/uji-teks', fn () => response('meledak', 400));

        $this->get('/api/v1/uji-teks')->assertStatus(400);

        $event = $this->event('http.request');

        $this->assertSame(400, $event['http']['status']);
        $this->assertArrayNotHasKey('error', $event);
    }

    public function test_a_json_error_response_that_is_not_an_object_is_ignored(): void
    {
        Route::middleware('api')->get(
            '/api/v1/uji-skalar',
            fn () => response('"cuma teks"', 400)->header('Content-Type', 'application/json'),
        );

        $this->get('/api/v1/uji-skalar')->assertStatus(400);

        $this->assertArrayNotHasKey('error', $this->event('http.request'));
    }

    public function test_the_content_length_header_is_used_for_the_response_size(): void
    {
        Route::middleware('api')->get(
            '/api/v1/uji-panjang',
            fn () => response('okay', 200)->header('Content-Length', '4'),
        );

        $this->get('/api/v1/uji-panjang')->assertOk();

        $this->assertSame(4, $this->event('http.request')['http']['bytes_out']);
    }
}
