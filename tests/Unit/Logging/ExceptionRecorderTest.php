<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Exceptions\Domain\DomainException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Logging\Axiom\AxiomLogger;
use App\Logging\Axiom\ExceptionRecorder;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Pelanggaran aturan bisnis DIBEDAKAN dari galat sungguhan.
 *
 * Kata sandi salah dan penawaran di bawah harga minimum adalah jalur normal
 * aplikasi ini. Kalau ikut dikirim sebagai `error`, mereka menenggelamkan galat
 * sungguhan dan membuat alert berbasis "jumlah error" tidak ada artinya — tapi
 * membuangnya juga salah, karena LONJAKAN-nya justru sinyal yang berguna.
 * Jalan tengahnya: event terpisah, level lebih rendah.
 */
final class ExceptionRecorderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('axiom.enabled', true);
        config()->set('axiom.capture.exceptions', true);

        app(AxiomLogger::class)->discard();
    }

    /** @return array<string, mixed> */
    private function recordAndTake(\Throwable $e): array
    {
        app(ExceptionRecorder::class)->record($e);

        $pending = app(AxiomLogger::class)->pending();

        $this->assertCount(1, $pending);

        return $pending[0];
    }

    public function test_a_business_rule_violation_is_a_notice_not_an_error(): void
    {
        $event = $this->recordAndTake(InvalidCredentialsException::make());

        $this->assertSame('error.domain', $event['event']);
        $this->assertSame('notice', $event['level']);
        $this->assertSame('invalid_credentials', $event['error']['domain_code']);
        $this->assertSame(401, $event['error']['http_status']);
    }

    public function test_domain_context_is_kept_because_it_explains_the_refusal(): void
    {
        $event = $this->recordAndTake(new class extends DomainException
        {
            public function errorCode(): string
            {
                return 'attempts_exhausted';
            }

            /** @return array<string, mixed> */
            public function context(): array
            {
                return ['attempts_left' => 0, 'email' => 'budi@contoh.test'];
            }
        });

        $this->assertSame(0, $event['error']['context']['attempts_left']);

        // Konteks domain melewati aturan nama kunci penuh: sebuah Action bisa
        // menaruh apa saja di sana.
        $this->assertArrayNotHasKey('email', $event['error']['context']);
        $this->assertStringNotContainsString('budi@contoh.test', (string) json_encode($event));
    }

    public function test_a_real_failure_is_an_error(): void
    {
        $event = $this->recordAndTake(new RuntimeException('koneksi redis hilang'));

        $this->assertSame('error.exception', $event['event']);
        $this->assertSame('error', $event['level']);
        $this->assertSame(RuntimeException::class, $event['error']['type']);
        $this->assertSame('koneksi redis hilang', $event['error']['message']);
    }

    /** 404 / 405: kesalahan klien, bukan kerusakan server. */
    public function test_a_client_side_http_exception_is_only_a_warning(): void
    {
        $event = $this->recordAndTake(new NotFoundHttpException('tidak ada'));

        $this->assertSame('error.http', $event['event']);
        $this->assertSame('warning', $event['level']);
        $this->assertSame(404, $event['error']['http_status']);
    }

    public function test_the_file_path_is_relative_and_the_line_is_kept(): void
    {
        $event = $this->recordAndTake(new RuntimeException('x'));

        $this->assertStringStartsWith('tests/Unit/Logging/', $event['error']['file']);
        $this->assertStringNotContainsString(base_path(), (string) json_encode($event));
        $this->assertIsInt($event['error']['line']);
    }

    /**
     * Galat unique constraint MySQL memuat nilai yang bentrok, dan di tabel
     * `users` nilai itu adalah alamat e-mail pengguna.
     */
    public function test_an_email_inside_a_database_error_message_is_scrubbed(): void
    {
        $event = $this->recordAndTake(new RuntimeException(
            "SQLSTATE[23000]: Duplicate entry 'budi@contoh.test' for key 'users_email_unique'",
        ));

        $this->assertStringNotContainsString('budi@contoh.test', (string) json_encode($event));
        $this->assertStringContainsString('users_email_unique', $event['error']['message']);
    }

    public function test_the_trace_is_present_and_carries_no_arguments(): void
    {
        $event = $this->recordAndTake($this->throwFrom('RahasiaKuat2026'));

        $this->assertNotEmpty($event['error']['trace']);
        $this->assertStringNotContainsString('RahasiaKuat2026', (string) json_encode($event['error']['trace']));
    }

    /**
     * Penyebab aslinya yang biasanya menjelaskan galat: sebuah QueryException
     * yang dibungkus jadi galat generik tidak berguna tanpa isi pembungkusnya.
     */
    public function test_the_previous_exception_is_included(): void
    {
        $previous = new RuntimeException('SQLSTATE[HY000] server has gone away');
        $event = $this->recordAndTake(new RuntimeException('gagal menyimpan task', 0, $previous));

        $this->assertSame(RuntimeException::class, $event['error']['previous']['type']);
        $this->assertStringContainsString('gone away', $event['error']['previous']['message']);
    }

    public function test_a_fatal_php_error_is_recorded_as_critical(): void
    {
        app(ExceptionRecorder::class)->recordFatal([
            'type' => E_ERROR,
            'message' => 'Allowed memory size of 134217728 bytes exhausted',
            'file' => base_path('app/Actions/Task/ListTasksAction.php'),
            'line' => 88,
        ]);

        $event = app(AxiomLogger::class)->pending()[0];

        $this->assertSame('error.fatal', $event['event']);
        $this->assertSame('critical', $event['level']);
        $this->assertSame('php.fatal:'.E_ERROR, $event['error']['type']);
        $this->assertSame('app/Actions/Task/ListTasksAction.php', $event['error']['file']);
        $this->assertSame(88, $event['error']['line']);
    }

    public function test_nothing_is_recorded_when_exception_capture_is_off(): void
    {
        config()->set('axiom.capture.exceptions', false);

        app(ExceptionRecorder::class)->record(new RuntimeException('x'));
        app(ExceptionRecorder::class)->recordFatal([
            'type' => E_ERROR, 'message' => 'x', 'file' => 'x', 'line' => 1,
        ]);

        $this->assertSame([], app(AxiomLogger::class)->pending());
    }

    /** Argumen sengaja diteruskan supaya ia muncul di frame trace. */
    private function throwFrom(string $secret): RuntimeException
    {
        try {
            $this->deeper($secret);
        } catch (RuntimeException $e) {
            return $e;
        }

        throw new RuntimeException('tidak tercapai');
    }

    private function deeper(string $secret): never
    {
        throw new RuntimeException('gagal');
    }
}
