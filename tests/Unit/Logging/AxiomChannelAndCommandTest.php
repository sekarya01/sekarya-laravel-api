<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Console\Commands\AxiomCommand;
use App\Logging\Axiom\AxiomLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Channel Monolog `axiom` dan perintah `sekarya:axiom`.
 *
 * Nilai channel ini: setiap `Log::info()` / `Log::error()` yang SUDAH ADA di
 * aplikasi ikut mendarat di Axiom tanpa satu baris kode aplikasi berubah, dan
 * membawa `request_id` yang sama dengan event HTTP-nya — jadi sebuah log bisa
 * dilihat dalam konteks request yang memancarkannya.
 */
final class AxiomChannelAndCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('axiom.enabled', true);
        config()->set('axiom.token', 'token-uji');
        config()->set('axiom.dataset', 'ds-uji');

        app(AxiomLogger::class)->discard();
    }

    private function channel(): LoggerInterface
    {
        return Log::channel('axiom');
    }

    public function test_an_application_log_line_becomes_an_axiom_event(): void
    {
        $this->channel()->warning('gagal menghubungi gateway');

        $event = app(AxiomLogger::class)->pending()[0];

        $this->assertSame('log.record', $event['event']);
        $this->assertSame('warning', $event['level']);
        $this->assertSame('gagal menghubungi gateway', $event['log']['message']);
        $this->assertSame(app(AxiomLogger::class)->requestId(), $event['request_id']);
    }

    /**
     * Konteks log adalah tempat kebocoran paling sering terjadi:
     * `Log::info('gagal', ['user' => $user])` mengirim seluruh model, termasuk
     * hash kata sandi. Karena itu konteks melewati aturan nama kunci PENUH,
     * bukan hanya penyaring pola.
     */
    public function test_log_context_passes_the_full_key_rules(): void
    {
        $this->channel()->error('gagal login', [
            'email' => 'budi@contoh.test',
            'password' => 'RahasiaKuat2026',
            'attempt' => 3,
        ]);

        $event = app(AxiomLogger::class)->pending()[0];
        $json = (string) json_encode($event);

        $this->assertStringNotContainsString('RahasiaKuat2026', $json);
        $this->assertStringNotContainsString('budi@contoh.test', $json);
        $this->assertSame(3, $event['log']['context']['attempt']);
        $this->assertSame('contoh.test', $event['log']['context']['email_domain']);
    }

    public function test_the_channel_respects_its_configured_level(): void
    {
        config()->set('logging.channels.axiom.level', 'error');

        Log::forgetChannel('axiom');
        $this->channel()->info('tidak cukup penting');
        $this->channel()->error('ini penting');

        $events = app(AxiomLogger::class)->pending();

        $this->assertCount(1, $events);
        $this->assertSame('error', $events[0]['level']);
    }

    public function test_the_channel_sends_nothing_when_log_capture_is_off(): void
    {
        config()->set('axiom.capture.logs', false);

        $this->channel()->error('apa pun');

        $this->assertSame([], app(AxiomLogger::class)->pending());
    }

    // ── Perintah artisan ────────────────────────────────────────────────────

    /**
     * Token TIDAK PERNAH dicetak utuh: perintah ini sering dijalankan di CI,
     * yang menyimpan keluarannya.
     */
    public function test_the_status_output_never_prints_the_token(): void
    {
        config()->set('axiom.token', 'axiom-token-sangat-rahasia-1234');

        $this->artisan('sekarya:axiom')
            ->expectsOutputToContain('terpasang')
            ->doesntExpectOutputToContain('axiom-token-sangat-rahasia')
            ->assertSuccessful();
    }

    public function test_the_status_says_plainly_when_no_token_is_configured(): void
    {
        config()->set('axiom.token', '');

        $this->artisan('sekarya:axiom')
            ->expectsOutputToContain('belum diisi')
            ->assertSuccessful();
    }

    /**
     * `--audit` adalah alasan utama perintah ini ada: klaim "PII sudah
     * disaring" harus bisa DILIHAT, bukan dipercaya.
     */
    /**
     * Kolom KIRI tabel audit memang mencetak nilai mentahnya — itu gunanya,
     * dan nilainya sintetis (lihat AxiomCommand). Yang diperiksa di sini
     * adalah penanda penyaringan benar-benar muncul, sehingga sebuah regresi
     * pada Redactor terlihat di layar orang yang menjalankan perintah ini.
     */
    public function test_the_audit_shows_that_filtering_actually_happened(): void
    {
        $this->artisan('sekarya:axiom --audit')
            ->expectsOutputToContain('[redacted]')
            ->expectsOutputToContain('_sha=')
            ->expectsOutputToContain('[scrubbed]')
            ->assertSuccessful();
    }

    /**
     * Jebakan yang benar-benar terjadi saat menyalakan integrasi ini: token
     * yang sudah benar diisi di atas, lalu sebuah placeholder kosong yang
     * tertinggal di bawah menimpanya — karena Dotenv memakai kemunculan
     * TERAKHIR. Gejalanya cuma "kredensial belum diisi" padahal tokennya jelas
     * terlihat di berkas, dan hanya ketahuan setelah membaca log.
     */
    public function test_duplicate_env_keys_are_detected_by_name_only(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');

        file_put_contents($path, implode("\n", [
            'APP_ENV=local',
            'AXIOM_TOKEN=xaat-contoh-bukan-token-sungguhan',
            'AXIOM_DATASET=logs',
            '# AXIOM_TOKEN=yang-dikomentari-tidak-dihitung',
            'AXIOM_TOKEN=',
            'AXIOM_ENABLED=true',
        ]));

        $duplicates = AxiomCommand::duplicateEnvKeys($path);

        unlink($path);

        $this->assertSame(['AXIOM_TOKEN'], $duplicates);
    }

    /**
     * Perintahnya harus BERTERIAK, bukan sekadar bisa mendeteksi: jebakan ini
     * hanya terlihat kalau orang yang menjalankannya diberi tahu.
     */
    public function test_the_command_shouts_about_a_duplicated_key(): void
    {
        $dir = sys_get_temp_dir().'/axiom-env-'.bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir.'/.env', "AXIOM_TOKEN=xaat-asli\nAXIOM_TOKEN=\n");

        $this->app->useEnvironmentPath($dir);

        $this->artisan('sekarya:axiom')
            ->expectsOutputToContain('AXIOM_TOKEN')
            ->doesntExpectOutputToContain('xaat-asli')
            ->assertSuccessful();

        unlink($dir.'/.env');
        rmdir($dir);
    }

    public function test_a_clean_env_file_reports_no_duplicates(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'env');
        file_put_contents($path, "AXIOM_TOKEN=x\nAXIOM_DATASET=y\n");

        $this->assertSame([], AxiomCommand::duplicateEnvKeys($path));

        unlink($path);
    }

    public function test_a_missing_env_file_is_not_an_error(): void
    {
        $this->assertSame([], AxiomCommand::duplicateEnvKeys('/tidak/ada/.env'));
    }

    /** Header memakai allowlist, jadi kredensial tidak akan pernah lolos. */
    public function test_the_audit_proves_credential_headers_are_dropped(): void
    {
        $this->artisan('sekarya:axiom --audit')
            ->doesntExpectOutputToContain('YA — BUG')
            ->assertSuccessful();
    }

    public function test_the_audit_sends_nothing(): void
    {
        Http::fake();

        $this->artisan('sekarya:axiom --audit')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_ping_sends_a_single_probe_event(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        $this->artisan('sekarya:axiom --ping')->assertSuccessful();

        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->data()[0]['event'] === 'axiom.ping');
    }

    public function test_ping_fails_loudly_when_axiom_is_disabled(): void
    {
        config()->set('axiom.enabled', false);
        Http::fake();

        $this->artisan('sekarya:axiom --ping')->assertFailed();

        Http::assertNothingSent();
    }

    public function test_ping_reports_a_rejection_as_a_failure(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('nope', 401)]);

        $this->artisan('sekarya:axiom --ping')->assertFailed();
    }
}
