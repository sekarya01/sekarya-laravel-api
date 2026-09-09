<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Logging\Axiom\AxiomLogger;
use App\Providers\AxiomServiceProvider;
use App\Support\TokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Bukti end-to-end: apa yang BENAR-BENAR terkirim ke Axiom saat API dipakai.
 *
 * Test unit membuktikan Redactor menyaring dengan benar. Kelas ini menjawab
 * pertanyaan yang berbeda dan lebih penting: apakah seluruh jalurnya benar
 * tersambung — middleware, listener, penangan exception — sehingga sebuah
 * request nyata tidak membocorkan apa pun.
 *
 * Cara membacanya: hampir semua test di sini menegaskan KETIADAAN. Sebuah
 * assertion yang gagal di sini berarti data pengguna sedang mengalir ke pihak
 * ketiga.
 */
final class AxiomObservabilityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'RahasiaKuat2026';

    private const EMAIL = 'budi.prasetyo@contoh.test';

    private const PHONE = '+628111222333';

    private const NAME = 'Budi Prasetyo';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('axiom.enabled', true);
        config()->set('axiom.token', 'token-uji');
        config()->set('axiom.dataset', 'ds-uji');
        config()->set('axiom.delivery', 'sync');

        Http::fake([
            'api.axiom.co/*' => Http::response('', 200),
            '*' => Http::response('', 200),
        ]);

        // Listener hanya di-boot saat Axiom menyala, dan phpunit.xml
        // mematikannya keras. Di-boot ulang di sini setelah config diubah.
        $this->app->register(AxiomServiceProvider::class, true);

        app(AxiomLogger::class)->discard();
    }

    /**
     * Seluruh event yang benar-benar dikirim ke Axiom selama test ini.
     *
     * @return array<int, array<string, mixed>>
     */
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

    /** Payload mentah yang terkirim — dipakai untuk menegaskan KETIADAAN. */
    private function sentJson(): string
    {
        return (string) json_encode($this->sentEvents());
    }

    /** @return array<string, mixed> */
    private function firstEvent(string $name): array
    {
        foreach ($this->sentEvents() as $event) {
            if ($event['event'] === $name) {
                return $event;
            }
        }

        $this->fail(sprintf(
            'event "%s" tidak terkirim. Yang terkirim: %s',
            $name,
            implode(', ', array_column($this->sentEvents(), 'event')) ?: '(tidak ada)',
        ));
    }

    /** @return array<string, mixed> */
    private function registrationPayload(): array
    {
        return [
            'name' => self::NAME,
            'email' => self::EMAIL,
            'phone' => self::PHONE,
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'city' => 'Jakarta',
        ];
    }

    // ── Gerbang ─────────────────────────────────────────────────────────────

    public function test_nothing_leaves_the_server_while_axiom_is_disabled(): void
    {
        config()->set('axiom.enabled', false);

        $this->postJson(route('v1.auth.register'), $this->registrationPayload())
            ->assertAccepted();

        $this->assertSame([], $this->sentEvents());
    }

    // ── Pendaftaran: payload paling sensitif di aplikasi ini ────────────────

    public function test_a_registration_never_leaks_the_password_email_phone_or_name(): void
    {
        $this->postJson(route('v1.auth.register'), $this->registrationPayload())
            ->assertAccepted();

        $json = $this->sentJson();

        $this->assertNotSame('', $json, 'harus ada event yang terkirim, kalau tidak test ini tidak membuktikan apa pun');
        $this->assertNotSame('[]', $json);

        $this->assertStringNotContainsString(self::PASSWORD, $json);
        $this->assertStringNotContainsString(self::EMAIL, $json);
        $this->assertStringNotContainsString('budi.prasetyo', strtolower($json));
        $this->assertStringNotContainsString(self::PHONE, $json);
        $this->assertStringNotContainsString('628111222333', $json);
        $this->assertStringNotContainsString(self::NAME, $json);
    }

    /**
     * Sisi lain dari test di atas: menyaring semuanya sampai tidak ada yang
     * tersisa juga kegagalan. Event harus tetap bisa dipakai men-debug.
     */
    public function test_the_registration_event_still_says_what_happened(): void
    {
        $this->postJson(route('v1.auth.register'), $this->registrationPayload());

        $event = $this->firstEvent('http.request');

        $this->assertSame('POST', $event['http']['method']);
        $this->assertSame('/api/v1/auth/register', $event['http']['path']);
        $this->assertSame('v1.auth.register', $event['http']['route']);
        $this->assertSame(202, $event['http']['status']);
        $this->assertIsInt($event['http']['duration_ms']);

        // Bentuk payload tetap terlihat — nama field, ada/tidaknya, panjangnya.
        // Itu yang menjawab "klien mengirim apa?" tanpa mengirim isinya.
        $body = $event['http']['body'];

        $this->assertSame('[redacted]', $body['password']);
        $this->assertSame('Jakarta', $body['city']);
        $this->assertArrayHasKey('email_sha', $body);
        $this->assertSame('contoh.test', $body['email_domain']);
        $this->assertArrayHasKey('name_sha', $body);
    }

    // ── Token dan header otorisasi ──────────────────────────────────────────

    /**
     * Body respons SUKSES tidak pernah dikirim, dan respons login berisi
     * sepasang token yang bisa dipakai langsung untuk menyamar sebagai
     * pengguna. Ini test yang membuktikan aturan itu benar-benar berlaku.
     */
    public function test_the_issued_tokens_never_appear_in_the_logs(): void
    {
        $this->activeUser(['email' => self::EMAIL, 'password' => bcrypt(self::PASSWORD)]);

        $response = $this->postJson(route('v1.auth.login'), [
            'email' => self::EMAIL,
            'password' => self::PASSWORD,
        ])->assertOk();

        $access = (string) $response->json('data.access_token');
        $longLived = (string) $response->json('data.long_lived_token');

        $this->assertNotSame('', $access);

        $json = $this->sentJson();

        $this->assertStringNotContainsString($access, $json);
        $this->assertStringNotContainsString($longLived, $json);
        $this->assertSame(200, $this->firstEvent('auth.login')['auth']['status']);

        // Rute login tidak terautentikasi, jadi belum ada `user_id` di sini —
        // yang membuktikan pengguna mana yang masuk adalah id di event
        // permintaan terautentikasi berikutnya (lihat test header otorisasi).
        $this->assertArrayNotHasKey('user_id', $this->firstEvent('http.request'));
    }

    /**
     * Aplikasi ini tidak pernah memanggil `Auth::attempt()`, jadi event auth
     * bawaan Laravel tidak menyala. Hasilnya diturunkan dari rute + status —
     * dan test ini yang menjaga jalur turunan itu tetap tersambung.
     */
    public function test_a_failed_login_is_recorded_with_the_reason_but_not_the_attempt(): void
    {
        $this->activeUser(['email' => self::EMAIL, 'password' => bcrypt(self::PASSWORD)]);

        $this->postJson(route('v1.auth.login'), [
            'email' => self::EMAIL,
            'password' => 'tebakan-penyerang',
        ])->assertUnauthorized();

        $event = $this->firstEvent('auth.login_failed');

        $this->assertSame('warning', $event['level']);
        $this->assertSame(401, $event['auth']['status']);

        // Membedakan "kata sandi salah" dari "akun belum aktif" saat
        // menyelidiki lonjakan kegagalan login.
        $this->assertSame('invalid_credentials', $event['auth']['reason']);

        // IP hadir sebagai prefix + pseudonim, bukan alamat lengkap.
        $this->assertArrayHasKey('ip_prefix', $event['auth']);
        $this->assertArrayNotHasKey('ip', $event['auth']);

        $this->assertStringNotContainsString('tebakan-penyerang', $this->sentJson());
    }

    public function test_registration_and_verification_outcomes_are_recorded(): void
    {
        $this->postJson(route('v1.auth.register'), $this->registrationPayload())->assertAccepted();

        $this->assertSame(202, $this->firstEvent('auth.registered')['auth']['status']);

        $this->postJson(route('v1.auth.verify-email'), [
            'email' => self::EMAIL,
            'code' => '000000',
        ])->assertUnprocessable();

        $this->assertSame('warning', $this->firstEvent('auth.verify_failed')['level']);
        $this->assertStringNotContainsString('000000', $this->sentJson());
    }

    public function test_the_authorization_header_is_never_forwarded(): void
    {
        $user = $this->activeUser();
        $token = app(TokenIssuer::class)->issuePair($user)['access']->plainTextToken;

        $this->app['auth']->forgetGuards();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'User-Agent' => 'Sekarya/1.2.0 (Android 14)',
        ])->getJson(route('v1.me.show'))->assertOk();

        $json = $this->sentJson();
        $event = $this->firstEvent('http.request');

        $this->assertStringNotContainsString($token, $json);
        $this->assertArrayNotHasKey('authorization', $event['http']['headers']);
        $this->assertSame('Sekarya/1.2.0 (Android 14)', $event['http']['headers']['user-agent']);
        $this->assertSame($user->getKey(), $event['user_id']);
    }

    // ── Korelasi ────────────────────────────────────────────────────────────

    /**
     * Laporan bug dari klien menyebut satu id, dan satu baris di Axiom langsung
     * ditemukan — tanpa menebak dari jam kejadian.
     */
    public function test_the_response_header_carries_the_id_that_the_event_was_logged_under(): void
    {
        $response = $this->getJson(route('v1.me.show'));

        $requestId = $response->headers->get('X-Request-Id');

        $this->assertNotNull($requestId);
        $this->assertSame($requestId, $this->firstEvent('http.request')['request_id']);
    }

    public function test_an_upstream_request_id_is_adopted_so_one_trace_spans_services(): void
    {
        $this->withHeaders(['X-Request-Id' => 'trace-dari-gateway-01'])
            ->getJson(route('v1.me.show'))
            ->assertHeader('X-Request-Id', 'trace-dari-gateway-01');

        $this->assertSame('trace-dari-gateway-01', $this->firstEvent('http.request')['request_id']);
    }

    public function test_a_malformed_upstream_request_id_is_ignored_rather_than_trusted(): void
    {
        $this->withHeaders(['X-Request-Id' => 'x'])->getJson(route('v1.me.show'));

        $this->assertNotSame('x', $this->firstEvent('http.request')['request_id']);
    }

    /** Semua event dalam satu request harus bisa dirangkai menjadi satu cerita. */
    public function test_every_event_in_one_request_shares_the_same_id(): void
    {
        $this->postJson(route('v1.auth.login'), ['email' => 'hantu@contoh.test', 'password' => 'salah']);

        $ids = array_unique(array_column($this->sentEvents(), 'request_id'));

        $this->assertGreaterThan(1, count($this->sentEvents()));
        $this->assertCount(1, $ids);
    }

    // ── Event keamanan ──────────────────────────────────────────────────────

    public function test_an_unauthenticated_call_is_recorded_as_a_security_event(): void
    {
        $this->getJson(route('v1.me.show'))->assertUnauthorized();

        $event = $this->firstEvent('security.unauthenticated');

        $this->assertSame('v1.me.show', $event['http']['route']);
        $this->assertSame(401, $event['http']['status']);
        $this->assertSame('notice', $event['level']);
    }

    /**
     * Nama field yang gagal validasi menjelaskan penolakan; nilai yang dikirim
     * pengguna tidak menambah apa pun untuk itu.
     */
    public function test_a_validation_failure_records_field_names_not_submitted_values(): void
    {
        $this->postJson(route('v1.auth.register'), [
            'name' => self::NAME,
            'email' => 'jelas-bukan-email',
            'password' => 'pendek',
        ])->assertUnprocessable();

        $event = $this->firstEvent('http.request');

        $this->assertContains('email', $event['error']['fields']);
        $this->assertStringNotContainsString('jelas-bukan-email', $this->sentJson());
        $this->assertStringNotContainsString('pendek', $this->sentJson());

        $this->assertSame(422, $this->firstEvent('security.validation_failed')['http']['status']);
    }

    public function test_hitting_the_rate_limit_is_recorded_as_a_warning(): void
    {
        $limit = (int) config('sekarya.rate_limits.login');

        for ($i = 0; $i <= $limit; $i++) {
            $this->postJson(route('v1.auth.login'), [
                'email' => 'hantu@contoh.test',
                'password' => 'salah',
            ]);
        }

        $event = $this->firstEvent('security.rate_limited');

        $this->assertSame(429, $event['http']['status']);
        $this->assertSame('warning', $event['level']);
    }

    // ── Galat ───────────────────────────────────────────────────────────────

    /**
     * Kata sandi salah adalah jalur normal aplikasi ini. Kalau ia dikirim
     * sebagai `error`, setiap alert berbasis "jumlah error" jadi tidak berarti.
     */
    public function test_a_business_refusal_is_logged_as_a_domain_event_not_an_error(): void
    {
        $this->activeUser(['email' => self::EMAIL, 'password' => bcrypt(self::PASSWORD)]);

        $this->postJson(route('v1.auth.login'), [
            'email' => self::EMAIL,
            'password' => 'salah-sekali',
        ])->assertUnauthorized();

        $event = $this->firstEvent('error.domain');

        $this->assertSame('notice', $event['level']);
        $this->assertSame('invalid_credentials', $event['error']['domain_code']);
        $this->assertStringNotContainsString('salah-sekali', $this->sentJson());
    }

    public function test_an_unhandled_exception_is_reported_with_a_trace(): void
    {
        Route::middleware('api')->get('/api/v1/uji-meledak', function (): never {
            throw new RuntimeException('gagal memproses pembayaran');
        });

        $this->getJson('/api/v1/uji-meledak')->assertStatus(500);

        $event = $this->firstEvent('error.exception');

        $this->assertSame('error', $event['level']);
        $this->assertSame(RuntimeException::class, $event['error']['type']);
        $this->assertSame('gagal memproses pembayaran', $event['error']['message']);
        $this->assertNotEmpty($event['error']['trace']);

        // Path absolut membocorkan struktur direktori dan nama pengguna sistem.
        $this->assertStringNotContainsString(base_path(), $this->sentJson());

        $this->assertSame(500, $this->firstEvent('http.request')['http']['status']);
    }

    // ── Kebisingan ──────────────────────────────────────────────────────────

    /**
     * Health check dipanggil tiap beberapa detik oleh load balancer; mencatatnya
     * hanya menghasilkan biaya dan menenggelamkan event yang berarti.
     */
    public function test_the_health_check_is_not_logged(): void
    {
        $this->get('/up')->assertOk();

        $this->assertSame([], $this->sentEvents());
    }
}
