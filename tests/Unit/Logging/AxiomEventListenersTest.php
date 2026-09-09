<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\Axiom\AxiomLogger;
use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use App\Providers\AxiomServiceProvider;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * Listener didaftarkan lewat event bawaan Laravel, bukan dengan menyisipkan
 * panggilan log ke dalam Action — Action di arsitektur ini HTTP-agnostik dan
 * hanya memuat aturan bisnis.
 *
 * Konsekuensinya, cakupan pencatatan bergantung pada listener ini benar-benar
 * terpasang dan benar-benar menyaring. Itu yang diuji di sini.
 */
final class AxiomEventListenersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('axiom.enabled', true);
        config()->set('axiom.token', 'token-uji');
        config()->set('axiom.dataset', 'ds-uji');

        // Provider mem-boot listener HANYA saat Axiom menyala, dan di test
        // Axiom dimatikan keras lewat phpunit.xml. Jadi ia di-boot ulang di
        // sini, setelah config diubah.
        $this->app->register(AxiomServiceProvider::class, true);

        app(AxiomLogger::class)->discard();
    }

    /** @return array<string, mixed> */
    private function eventNamed(string $name): array
    {
        foreach (app(AxiomLogger::class)->pending() as $event) {
            if ($event['event'] === $name) {
                return $event;
            }
        }

        $this->fail(sprintf(
            'event "%s" tidak dipancarkan. Yang ada: %s',
            $name,
            implode(', ', array_column(app(AxiomLogger::class)->pending(), 'event')) ?: '(tidak ada)',
        ));
    }

    /**
     * `users.id` adalah integer auto-increment; `ulid` hanya route key publik.
     * Yang dicatat adalah id internal itu — pengenal, bukan identitas orang.
     */
    private function user(int $id = 4242): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    // ── Auth ────────────────────────────────────────────────────────────────

    public function test_a_successful_login_records_the_internal_id_only(): void
    {
        event(new Login('sanctum', $this->user(), false));

        $event = $this->eventNamed('auth.login');

        $this->assertSame('sanctum', $event['auth']['guard']);
        $this->assertSame(4242, $event['auth']['user_id']);
    }

    public function test_logout_is_recorded(): void
    {
        event(new Logout('sanctum', $this->user()));

        $this->assertSame(4242, $this->eventNamed('auth.logout')['auth']['user_id']);
    }

    /**
     * `Failed::$credentials` MEMUAT KATA SANDI dalam bentuk asli. Ini test
     * keamanan yang paling langsung di kelas ini.
     */
    public function test_a_failed_login_never_carries_the_submitted_password(): void
    {
        event(new Failed('sanctum', null, [
            'email' => 'budi@contoh.test',
            'password' => 'RahasiaKuat2026',
        ]));

        $event = $this->eventNamed('auth.failed');
        $json = (string) json_encode($event);

        $this->assertStringNotContainsString('RahasiaKuat2026', $json);
        $this->assertStringNotContainsString('budi@contoh.test', $json);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $event['auth']['identifier_sha']);
        $this->assertSame('warning', $event['level']);
    }

    /**
     * Pertanyaan yang selalu muncul saat menyelidiki percobaan pembajakan:
     * apakah akunnya memang ada?
     */
    public function test_a_failed_login_says_whether_the_account_exists(): void
    {
        event(new Failed('sanctum', null, ['email' => 'hantu@contoh.test']));
        $this->assertFalse($this->eventNamed('auth.failed')['auth']['user_exists']);

        app(AxiomLogger::class)->discard();

        event(new Failed('sanctum', $this->user(), ['email' => 'budi@contoh.test']));
        $this->assertTrue($this->eventNamed('auth.failed')['auth']['user_exists']);
    }

    public function test_auth_events_are_not_recorded_when_the_group_is_off(): void
    {
        config()->set('axiom.capture.auth', false);
        $this->app->register(AxiomServiceProvider::class, true);
        app(AxiomLogger::class)->discard();

        event(new Login('sanctum', $this->user(), false));

        $this->assertSame([], app(AxiomLogger::class)->pending());
    }

    // ── Query lambat ────────────────────────────────────────────────────────

    /**
     * BINDINGS TIDAK PERNAH IKUT. Di situlah nilai sebenarnya berada — alamat
     * e-mail pada `where email = ?`, hash kata sandi pada sebuah update, NIK
     * pada pencarian duplikat.
     */
    public function test_a_slow_query_is_recorded_without_its_bindings(): void
    {
        config()->set('axiom.thresholds.slow_query_ms', 100);

        event(new QueryExecuted(
            'select * from `users` where `email` = ? and `document_number_hash` = ?',
            ['budi@contoh.test', 'a1b2c3'],
            250.5,
            DB::connection(),
        ));

        $event = $this->eventNamed('db.slow_query');
        $json = (string) json_encode($event);

        $this->assertStringNotContainsString('budi@contoh.test', $json);
        $this->assertStringNotContainsString('a1b2c3', $json);
        $this->assertSame(2, $event['db']['bindings_count'], 'jumlahnya berguna, isinya tidak');
        $this->assertStringContainsString('where `email` = ?', $event['db']['sql']);
        $this->assertSame(250.5, $event['db']['time_ms']);
        $this->assertSame('warning', $event['level']);
    }

    public function test_a_fast_query_is_not_recorded_at_all(): void
    {
        config()->set('axiom.thresholds.slow_query_ms', 200);

        event(new QueryExecuted('select 1', [], 5.0, DB::connection()));

        $this->assertSame([], app(AxiomLogger::class)->pending());
    }

    /**
     * Listener query dipanggil untuk SETIAP kueri, jadi ia tidak boleh
     * terpasang saat tidak dipakai — biayanya nyata di endpoint yang sibuk.
     *
     * Diukur sebagai SELISIH pada aplikasi yang baru di-boot, bukan dengan
     * `hasListeners()`: framework sendiri sudah memasang satu listener di event
     * ini, jadi "ada listener" tidak membuktikan apa pun. Dan listener yang
     * terlanjur terpasang tidak bisa dicabut, sehingga mengubah config pada
     * aplikasi test ini lalu mendaftar ulang juga tidak membuktikan apa pun.
     */
    public function test_the_provider_attaches_no_listener_while_axiom_is_disabled(): void
    {
        $fresh = $this->createApplication();

        // Keadaan default: AXIOM_ENABLED=false dari phpunit.xml.
        $this->assertFalse($fresh['config']->get('axiom.enabled'));

        $baseline = count($fresh['events']->getListeners(QueryExecuted::class));

        $fresh['config']->set('axiom.enabled', true);
        $fresh->register(AxiomServiceProvider::class, true);

        $this->assertSame(
            $baseline + 1,
            count($fresh['events']->getListeners(QueryExecuted::class)),
            'menyalakan Axiom harus menambah tepat satu listener kueri',
        );
    }

    // ── Job ─────────────────────────────────────────────────────────────────

    private function job(string $name = 'App\Jobs\SendMail'): Job
    {
        $job = Mockery::mock(Job::class);
        $job->allows('resolveName')->andReturns($name);
        $job->allows('getQueue')->andReturns('default');
        $job->allows('attempts')->andReturns(2);
        $job->allows('uuid')->andReturns('9f1e-uuid');

        return $job;
    }

    public function test_a_processed_job_is_recorded(): void
    {
        event(new JobProcessed('database', $this->job()));

        $event = $this->eventNamed('job.processed');

        $this->assertSame('App\Jobs\SendMail', $event['job']['name']);
        $this->assertSame('default', $event['job']['queue']);
        $this->assertSame(2, $event['job']['attempts']);
    }

    public function test_a_failed_job_carries_the_exception_and_its_trace(): void
    {
        event(new JobFailed('database', $this->job(), new RuntimeException('smtp menolak')));

        $event = $this->eventNamed('job.failed');

        $this->assertSame('error', $event['level']);
        $this->assertSame(RuntimeException::class, $event['error']['type']);
        $this->assertSame('smtp menolak', $event['error']['message']);
        $this->assertNotEmpty($event['error']['trace']);
    }

    /** Job pengirim Axiom sendiri tidak dicatat — itu akan berputar. */
    public function test_the_axiom_shipping_job_does_not_log_itself(): void
    {
        event(new JobProcessed('database', $this->job('App\Jobs\ShipAxiomBatch')));

        $this->assertSame([], app(AxiomLogger::class)->pending());
    }

    // ── Panggilan keluar ────────────────────────────────────────────────────

    public function test_an_outbound_call_records_host_and_status_but_not_the_query_secret(): void
    {
        Http::fake(['gateway.test/*' => Http::response('ok', 201)]);

        Http::get('https://gateway.test/v1/charge', ['api_key' => 'rahasia-kunci', 'amount' => 5000]);

        $event = $this->eventNamed('outbound.response');

        $this->assertSame('gateway.test', $event['outbound']['host']);
        $this->assertSame('/v1/charge', $event['outbound']['path']);
        $this->assertSame(201, $event['outbound']['status']);
        $this->assertStringNotContainsString('rahasia-kunci', (string) json_encode($event));
    }

    /**
     * `Password::uncompromised()` memanggil
     * `api.pwnedpasswords.com/range/46B35`, dan lima karakter itu adalah awalan
     * SHA-1 KATA SANDI yang baru diketik pengguna.
     *
     * Model k-anonymity HIBP membuatnya aman dikirim ke HIBP. Mengirimnya ke
     * Axiom adalah hal lain: di sana ia duduk dalam satu `request_id` dengan
     * pseudonim penggunanya. Tidak ada penyaring berbasis pola yang bisa
     * mengenali lima karakter heksadesimal, jadi host-nya disebut eksplisit.
     */
    public function test_a_password_derived_outbound_path_is_never_recorded(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response("0018A45C4D1DEF81644B54AB7F969B88D65:1\n", 200)]);

        Http::get('https://api.pwnedpasswords.com/range/46B35');

        $event = $this->eventNamed('outbound.response');

        $this->assertSame('api.pwnedpasswords.com', $event['outbound']['host']);
        $this->assertSame('[redacted]', $event['outbound']['path']);
        $this->assertStringNotContainsString('46B35', (string) json_encode($event));
    }

    public function test_a_failing_outbound_call_is_a_warning(): void
    {
        Http::fake(['gateway.test/*' => Http::response('nope', 502)]);

        Http::get('https://gateway.test/v1/charge');

        $this->assertSame('warning', $this->eventNamed('outbound.response')['level']);
    }

    // ── Surat & notifikasi ──────────────────────────────────────────────────

    /**
     * Subjek surat verifikasi di aplikasi ini berbunyi
     * "Kode verifikasi Sekarya: 623862" — ia MEMUAT kredensialnya. Karena itu
     * subjek tidak pernah ikut, dan test ini yang menjaganya.
     */
    public function test_mail_records_recipients_as_pseudonyms_and_never_the_subject(): void
    {
        $email = (new Email)
            ->to('budi@contoh.test')
            ->subject('Kode verifikasi Sekarya: 623862')
            ->text('kodenya 623862');

        event(new MessageSending($email));

        $event = $this->eventNamed('mail.sending');
        $json = (string) json_encode($event);

        $this->assertStringNotContainsString('623862', $json);
        $this->assertStringNotContainsString('Kode verifikasi', $json);
        $this->assertStringNotContainsString('budi@contoh.test', $json);
        $this->assertSame(1, $event['mail']['recipients']);
        $this->assertSame(['contoh.test'], $event['mail']['to_domains']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $event['mail']['to_sha'][0]);
    }

    public function test_a_sent_notification_records_the_class_and_channel(): void
    {
        event(new NotificationSent(
            $this->user(),
            new VerificationCodeNotification('623862', 15),
            'mail',
        ));

        $event = $this->eventNamed('mail.notification_sent');

        $this->assertSame(VerificationCodeNotification::class, $event['mail']['notification']);
        $this->assertSame('mail', $event['mail']['channel']);
        $this->assertSame('4242', $event['mail']['notifiable']);
        $this->assertStringNotContainsString('623862', (string) json_encode($event));
    }

    // ── Console ─────────────────────────────────────────────────────────────

    public function test_only_a_failing_command_is_recorded(): void
    {
        event(new CommandFinished('sekarya:demo', new ArrayInput([]), new NullOutput, 0));

        $this->assertSame([], app(AxiomLogger::class)->pending());

        event(new CommandFinished('sekarya:demo', new ArrayInput([]), new NullOutput, 1));

        $event = $this->eventNamed('console.command_failed');

        $this->assertSame('sekarya:demo', $event['console']['command']);
        $this->assertSame(1, $event['console']['exit_code']);
        $this->assertSame('error', $event['level']);
    }
}
