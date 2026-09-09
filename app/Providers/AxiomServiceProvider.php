<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\ShipAxiomBatch;
use App\Logging\Axiom\AxiomLogger;
use App\Logging\Axiom\ExceptionRecorder;
use App\Logging\Axiom\Redactor;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Events\Terminating;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mime\Address;
use Throwable;

/**
 * Menyambungkan seluruh sumber event ke AxiomLogger.
 *
 * Kenapa lewat event bawaan Laravel dan bukan dengan menyisipkan panggilan log
 * ke dalam Action: Action di arsitektur ini HTTP-agnostik dan hanya memuat
 * aturan bisnis. Menaruh pemanggilan observability di dalamnya akan mencampur
 * infrastruktur ke lapisan yang paling ingin dijaga bersih, dan setiap Action
 * baru harus ingat melakukannya. Lewat event, cakupannya lengkap sejak awal
 * dan nol baris kode bisnis berubah.
 *
 * Semua listener didaftarkan HANYA bila kelompoknya menyala di config. Listener
 * `QueryExecuted` khususnya tidak boleh terpasang saat tidak dipakai: ia
 * dipanggil untuk setiap kueri, dan biayanya nyata pada endpoint yang sibuk.
 */
final class AxiomServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton, dan ini WAJIB: satu buffer per proses. Kalau tiap
        // penyuntikan menghasilkan instance baru, setiap sumber event akan
        // mengirim batch-nya sendiri dan `request_id` yang menyatukan mereka
        // menjadi berbeda-beda — jejak satu request pecah menjadi puluhan.
        $this->app->singleton(Redactor::class);
        $this->app->singleton(AxiomLogger::class);
        $this->app->singleton(ExceptionRecorder::class);
    }

    public function boot(): void
    {
        $logger = $this->app->make(AxiomLogger::class);

        if (! $logger->enabled()) {
            return;
        }

        // Berlaku untuk console dan queue worker juga, bukan hanya HTTP —
        // di jalur HTTP middleware sudah memanggilnya lebih dulu, dan flush
        // bersifat idempoten karena buffer dikosongkan saat dikirim.
        Event::listen(Terminating::class, static fn () => $logger->flush());

        $this->registerFatalErrorHandler();

        $this->registerAuthListeners($logger);
        $this->registerJobListeners($logger);
        $this->registerQueryListener($logger);
        $this->registerOutboundHttpListeners($logger);
        $this->registerMailListeners($logger);
        $this->registerConsoleListeners($logger);
    }

    /**
     * Galat fatal: kehabisan memori, batas waktu eksekusi, galat kompilasi.
     *
     * Nilai utamanya bukan pesan galatnya — Laravel biasanya menangkap itu —
     * tapi FLUSH-nya. Tanpa hook ini, proses yang mati fatal membawa hilang
     * seluruh event request tersebut, termasuk query lambat dan panggilan
     * keluar yang menjelaskan apa yang sedang dikerjakan saat memori habis.
     */
    private function registerFatalErrorHandler(): void
    {
        register_shutdown_function($this->handleShutdown(...));
    }

    /**
     * Method publik, bukan closure di dalam `register_shutdown_function` —
     * supaya jalur ini bisa DIJALANKAN oleh test. Sebuah penangan galat fatal
     * yang tidak pernah diuji adalah penangan yang tidak diketahui bekerja,
     * dan ia justru hanya dipanggil pada saat terburuk.
     *
     * `$error` bisa disuntikkan; secara default dibaca dari error_get_last().
     *
     * @param  array{type: int, message: string, file: string, line: int}|null  $error
     */
    public function handleShutdown(?array $error = null): void
    {
        try {
            $error ??= error_get_last();

            $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

            if ($error !== null && in_array($error['type'], $fatal, true)) {
                $this->app->make(ExceptionRecorder::class)->recordFatal($error);
            }

            $this->app->make(AxiomLogger::class)->flush();
        } catch (Throwable) {
            // Berjalan setelah proses sudah runtuh. Tidak ada yang bisa
            // dilakukan dengan kegagalan di sini selain menelannya.
        }
    }

    private function registerAuthListeners(AxiomLogger $logger): void
    {
        if (! $logger->capturing('auth')) {
            return;
        }

        $redactor = $logger->redactor();

        Event::listen(Login::class, static fn (Login $e) => $logger->event('auth.login', [
            'auth' => array_filter([
                'guard' => $e->guard,
                'user_id' => $e->user->getAuthIdentifier(),
            ], static fn (mixed $v): bool => $v !== null),
        ]));

        Event::listen(Logout::class, static fn (Logout $e) => $logger->event('auth.logout', [
            'auth' => array_filter([
                'guard' => $e->guard,
                'user_id' => $e->user?->getAuthIdentifier(),
            ], static fn (mixed $v): bool => $v !== null),
        ]));

        Event::listen(OtherDeviceLogout::class, static fn (OtherDeviceLogout $e) => $logger->event(
            'auth.other_device_logout',
            ['auth' => ['guard' => $e->guard, 'user_id' => $e->user->getAuthIdentifier()]],
        ));

        /*
         * PENTING: di aplikasi ini ketiga listener di atas TIDAK PERNAH menyala.
         *
         * `LoginAction` memverifikasi hash sendiri lalu menerbitkan token
         * Sanctum; `Auth::attempt()` tidak pernah dipanggil, jadi Laravel tidak
         * pernah memancarkan Login/Logout/Failed. Mereka tetap dipasang supaya
         * jalur berbasis guard (mis. panel admin ber-sesi) langsung tercatat
         * begitu ditambahkan.
         *
         * Catatan auth yang SUNGGUHAN untuk API ini diturunkan dari nama rute
         * dan status respons di AxiomRequestLogger — lihat `axiom.auth_routes`.
         * Jangan hapus jalur itu dengan anggapan listener ini menggantikannya.
         */

        /*
         * Login gagal.
         *
         * `Failed::$credentials` MEMUAT KATA SANDI dalam bentuk asli. Array itu
         * tidak pernah diteruskan ke event — hanya pengenalnya yang diambil,
         * dan itu pun sebagai pseudonim. Melewatkannya ke Redactor::payload()
         * pun sebenarnya cukup (kunci `password` ada di denylist), tapi
         * bergantung pada denylist untuk nilai yang sudah kita tahu berbahaya
         * adalah pertahanan yang salah tempat.
         */
        Event::listen(Failed::class, static function (Failed $e) use ($logger, $redactor): void {
            $identifier = $e->credentials['email'] ?? $e->credentials['username'] ?? null;

            $logger->event('auth.failed', [
                'auth' => array_filter([
                    'guard' => $e->guard,
                    'user_id' => $e->user?->getAuthIdentifier(),
                    'identifier_sha' => is_string($identifier) && $identifier !== ''
                        ? $redactor->pseudonym($identifier)
                        : null,

                    // Menjawab pertanyaan yang selalu muncul saat menyelidiki
                    // percobaan pembajakan: apakah akunnya ada?
                    'user_exists' => $e->user !== null,
                ], static fn (mixed $v): bool => $v !== null),
            ], 'warning');
        });
    }

    private function registerJobListeners(AxiomLogger $logger): void
    {
        if (! $logger->capturing('jobs')) {
            return;
        }

        $redactor = $logger->redactor();

        Event::listen(JobQueued::class, static function (JobQueued $e) use ($logger): void {
            if ($e->job instanceof ShipAxiomBatch) {
                return;
            }

            $logger->event('job.queued', ['job' => array_filter([
                'name' => is_object($e->job) ? $e->job::class : (string) $e->job,
                'queue' => $e->queue,
                'connection' => $e->connectionName,
                'delay' => is_int($e->delay) ? $e->delay : null,
            ], static fn (mixed $v): bool => $v !== null)]);
        });

        Event::listen(JobProcessing::class, static function (JobProcessing $e) use ($logger): void {
            if (self::isOwnJob($e->job)) {
                return;
            }

            $logger->event('job.processing', ['job' => self::jobFields($e->job, $e->connectionName)]);
        });

        Event::listen(JobProcessed::class, static function (JobProcessed $e) use ($logger): void {
            if (self::isOwnJob($e->job)) {
                return;
            }

            $logger->event('job.processed', ['job' => self::jobFields($e->job, $e->connectionName)]);
        });

        Event::listen(JobFailed::class, static function (JobFailed $e) use ($logger, $redactor): void {
            if (self::isOwnJob($e->job)) {
                return;
            }

            $logger->event('job.failed', [
                'job' => self::jobFields($e->job, $e->connectionName),
                'error' => [
                    'type' => $e->exception::class,
                    'message' => $redactor->text($e->exception->getMessage()),
                    'trace' => $redactor->trace($e->exception->getTrace()),
                ],
            ], 'error');
        });
    }

    /**
     * Query yang lambat saja.
     *
     * BINDINGS TIDAK PERNAH IKUT. Di situlah nilai sebenarnya berada — alamat
     * e-mail pada `where email = ?`, hash kata sandi pada sebuah update, NIK
     * pada pencarian duplikat. SQL-nya sendiri, dengan tanda `?`, sudah cukup
     * untuk mengenali kueri mana yang lambat dan itulah satu-satunya yang
     * dibutuhkan untuk memperbaikinya.
     */
    private function registerQueryListener(AxiomLogger $logger): void
    {
        if (! $logger->capturing('slow_queries')) {
            return;
        }

        $redactor = $logger->redactor();
        $threshold = (float) config('axiom.thresholds.slow_query_ms', 200);
        $maxSql = (int) config('axiom.limits.max_sql_length', 2000);

        Event::listen(QueryExecuted::class, static function (QueryExecuted $e) use ($logger, $redactor, $threshold, $maxSql): void {
            if ($e->time < $threshold) {
                return;
            }

            $logger->event('db.slow_query', ['db' => [
                'connection' => $e->connectionName,
                'time_ms' => round($e->time, 2),
                'sql' => $redactor->text($e->sql, $maxSql),
                'bindings_count' => count($e->bindings),
            ]], 'warning');
        });
    }

    private function registerOutboundHttpListeners(AxiomLogger $logger): void
    {
        if (! $logger->capturing('outbound_http')) {
            return;
        }

        $redactor = $logger->redactor();
        $slowMs = (int) config('axiom.thresholds.slow_outbound_ms', 2000);

        Event::listen(ResponseReceived::class, static function (ResponseReceived $e) use ($logger, $slowMs): void {
            $uri = $e->request->toPsrRequest()->getUri();
            $stats = $e->response->transferStats;
            $ms = $stats === null ? null : (int) round($stats->getTransferTime() * 1000);
            $slow = $ms !== null && $ms >= $slowMs;

            $logger->event('outbound.response', ['outbound' => array_filter([
                'method' => $e->request->method(),
                'host' => $uri->getHost(),
                'path' => self::outboundPath($uri->getHost(), $uri->getPath()),
                'status' => $e->response->status(),
                'duration_ms' => $ms,
                'slow' => $slow ?: null,
            ], static fn (mixed $v): bool => $v !== null)],
                $e->response->failed() ? 'warning' : 'info');
        });

        Event::listen(ConnectionFailed::class, static function (ConnectionFailed $e) use ($logger, $redactor): void {
            $uri = $e->request->toPsrRequest()->getUri();

            $logger->event('outbound.connection_failed', [
                'outbound' => [
                    'method' => $e->request->method(),
                    'host' => $uri->getHost(),
                    'path' => self::outboundPath($uri->getHost(), $uri->getPath()),
                ],
                'error' => [
                    'type' => $e->exception::class,
                    'message' => $redactor->text($e->exception->getMessage()),
                ],
            ], 'error');
        });
    }

    /**
     * Surat & notifikasi.
     *
     * SUBJECT TIDAK IKUT — dan di aplikasi ini itu bukan kehati-hatian
     * abstrak: subjek surat verifikasi berbunyi "Kode verifikasi Sekarya:
     * 623862". Mengirim subjek ke Axiom berarti menyalin kode aktivasi akun
     * setiap pengguna baru ke pihak ketiga. Penerima pun hanya dikirim sebagai
     * pseudonim dan domain.
     */
    private function registerMailListeners(AxiomLogger $logger): void
    {
        if (! $logger->capturing('mail')) {
            return;
        }

        $redactor = $logger->redactor();

        Event::listen(MessageSending::class, static function (MessageSending $e) use ($logger, $redactor): void {
            $to = $e->message->getTo();

            $logger->event('mail.sending', ['mail' => array_filter([
                'recipients' => count($to),
                'to_sha' => array_map(
                    static fn (Address $a): string => $redactor->pseudonym($a->getAddress()),
                    array_slice($to, 0, 5),
                ),
                'to_domains' => array_values(array_unique(array_map(
                    static fn (Address $a): string => (string) (
                        $redactor->email($a->getAddress())['domain'] ?? 'unknown'
                    ),
                    array_slice($to, 0, 5),
                ))),
            ], static fn (mixed $v): bool => $v !== null && $v !== [])]);
        });

        Event::listen(NotificationSent::class, static fn (NotificationSent $e) => $logger->event(
            'mail.notification_sent',
            ['mail' => [
                'notification' => $e->notification::class,
                'channel' => $e->channel,
                'notifiable' => self::notifiableId($e->notifiable),
            ]],
        ));

        Event::listen(NotificationFailed::class, static fn (NotificationFailed $e) => $logger->event(
            'mail.notification_failed',
            ['mail' => [
                'notification' => $e->notification::class,
                'channel' => $e->channel,
                'notifiable' => self::notifiableId($e->notifiable),
            ]],
            'error',
        ));
    }

    private function registerConsoleListeners(AxiomLogger $logger): void
    {
        if (! $logger->capturing('console')) {
            return;
        }

        $redactor = $logger->redactor();

        // Hanya perintah yang GAGAL. Argumen perintah tidak ikut: sebuah
        // `artisan user:reset --password=...` akan mengirimkannya apa adanya.
        Event::listen(CommandFinished::class, static function (CommandFinished $e) use ($logger): void {
            if ($e->exitCode === 0) {
                return;
            }

            $logger->event('console.command_failed', ['console' => [
                'command' => (string) $e->command,
                'exit_code' => $e->exitCode,
            ]], 'error');
        });

        Event::listen(ScheduledTaskFailed::class, static fn (ScheduledTaskFailed $e) => $logger->event(
            'console.scheduled_task_failed',
            [
                'console' => ['task' => $e->task->getSummaryForDisplay()],
                'error' => [
                    'type' => $e->exception::class,
                    'message' => $redactor->text($e->exception->getMessage()),
                ],
            ],
            'error',
        ));
    }

    /**
     * Path panggilan keluar, kecuali untuk host yang path-nya sendiri rahasia.
     *
     * `Password::uncompromised()` memanggil
     * `api.pwnedpasswords.com/range/<5 hex pertama SHA-1 kata sandi>`. Lima
     * karakter itu tidak bisa dibedakan dari potongan teks biasa oleh penyaring
     * mana pun, jadi satu-satunya cara menanganinya adalah menyebut host-nya
     * secara eksplisit. Daftarnya di `config/axiom.php`.
     */
    private static function outboundPath(string $host, string $path): string
    {
        /** @var array<int, string> $hosts */
        $hosts = (array) config('axiom.redact_outbound_path_for', []);

        return in_array($host, $hosts, true) ? Redactor::REDACTED : $path;
    }

    /** Job pengirim Axiom sendiri tidak dicatat — itu akan berputar. */
    private static function isOwnJob(Job $job): bool
    {
        return str_contains($job->resolveName(), 'ShipAxiomBatch');
    }

    /** @return array<string, mixed> */
    private static function jobFields(Job $job, ?string $connection): array
    {
        return array_filter([
            'name' => $job->resolveName(),
            'queue' => $job->getQueue(),
            'connection' => $connection,
            'attempts' => $job->attempts(),
            'uuid' => $job->uuid(),
        ], static fn (mixed $v): bool => $v !== null);
    }

    /** Id internal penerima notifikasi — bukan alamatnya. */
    private static function notifiableId(mixed $notifiable): ?string
    {
        return is_object($notifiable) && method_exists($notifiable, 'getKey')
            ? (string) $notifiable->getKey()
            : null;
    }
}
