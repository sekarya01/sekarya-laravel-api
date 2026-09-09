<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Logging\Axiom\AxiomLogger;
use App\Logging\Axiom\Redactor;
use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Satu event per request HTTP, plus event keamanan untuk 401/403/422/429.
 *
 * Dipasang paling luar pada grup `api` supaya durasi yang tercatat adalah
 * durasi yang benar-benar dirasakan klien — termasuk waktu yang dihabiskan
 * middleware lain seperti throttle dan autentikasi. Dipasang di dalam, sebuah
 * request yang ditolak 429 tidak akan pernah tercatat sama sekali, padahal
 * itu justru event yang paling ingin dilihat.
 *
 * Yang TIDAK dicatat, dan alasannya:
 *
 *  - Body respons sukses. Isinya data pengguna; nilai debug-nya kecil
 *    dibanding risikonya. Body respons GALAT dicatat, karena `{message, code,
 *    errors}` justru penjelasan kenapa request gagal.
 *  - Nama berkas yang diunggah. "KTP_Budi_Prasetyo.jpg" adalah PII di dalam
 *    metadata; hanya ukuran dan tipe MIME yang dipakai saat men-debug unggahan.
 *  - Header di luar allowlist, sehingga `Authorization` dan `Cookie` tidak
 *    pernah bisa ikut.
 */
final class AxiomRequestLogger
{
    public function __construct(
        private readonly AxiomLogger $logger,
        private readonly Redactor $redactor,
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Waktu mulai dititipkan ke AxiomLogger, bukan disimpan di sini:
        // Laravel membuat instance middleware BARU untuk `terminate()`, jadi
        // properti instance tidak selamat dari `handle()` ke `terminate()`.
        $this->logger->markStart(microtime(true));

        // Hormati request id dari gateway/klien bila ada, supaya satu jejak
        // tetap menyatu melewati beberapa layanan.
        $incoming = (string) $request->headers->get('X-Request-Id', '');

        if ($incoming !== '' && preg_match('/^[A-Za-z0-9._\-]{8,64}$/', $incoming) === 1) {
            $this->logger->setRequestId($incoming);
        }

        $response = $next($request);

        // Klien bisa menyebut id ini di laporan bug, dan satu baris di Axiom
        // langsung ditemukan tanpa menebak dari jam kejadian.
        $response->headers->set('X-Request-Id', $this->logger->requestId());

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $this->record($request, $response);
        } catch (Throwable) {
            // Pencatatan tidak boleh pernah menjadi penyebab galat. Request
            // sudah selesai dilayani di titik ini; menelan kegagalan di sini
            // hanya berarti kehilangan satu event.
        } finally {
            $this->logger->flush();
        }
    }

    private function record(Request $request, Response $response): void
    {
        if (! $this->logger->capturing('http') || $this->ignored($request)) {
            return;
        }

        $status = $response->getStatusCode();
        $durationMs = $this->logger->elapsedMs() ?? 0;
        $slow = $durationMs >= (int) $this->config->get('axiom.thresholds.slow_request_ms', 1000);

        if (! $this->shouldSample($status, $slow)) {
            return;
        }

        $user = $request->user();

        // Identitas internal (ULID), bukan identitas orang. Inilah yang membuat
        // "request siapa ini" tetap terjawab tanpa satu pun PII terkirim.
        if ($user !== null) {
            $this->logger->withContext(['user_id' => $user->getAuthIdentifier()]);
        }

        $this->logger->event(
            'http.request',
            array_filter([
                'http' => array_filter([
                    'method' => $request->getMethod(),
                    'path' => '/'.ltrim($request->path(), '/'),
                    'route' => $request->route()?->getName(),
                    'status' => $status,
                    'duration_ms' => $durationMs,
                    'slow' => $slow ?: null,
                    'bytes_out' => $this->responseSize($response),
                    'query' => $this->query($request),
                    'body' => $this->body($request),
                    'files' => $this->files($request),
                    'headers' => $this->redactor->headers($request->headers->all()),
                    ...$this->redactor->ip($request->ip()),
                ], static fn (mixed $v): bool => $v !== null && $v !== []),
                'error' => $this->errorPayload($response, $status),
            ], static fn (mixed $v): bool => $v !== null),
            $this->levelFor($status),
        );

        $this->recordSecurityEvent($request, $status);
        $this->recordAuthOutcome($request, $response, $status);
    }

    /**
     * Hasil autentikasi, diturunkan dari nama rute dan status.
     *
     * API ini tidak pernah memanggil `Auth::attempt()` — `LoginAction`
     * memverifikasi hash sendiri lalu menerbitkan token Sanctum. Akibatnya
     * `Illuminate\Auth\Events\Login` dan `Failed` TIDAK PERNAH menyala, dan
     * pencatatan auth yang hanya bergantung padanya akan kosong selamanya.
     *
     * Menambahkan pemanggilan event ke dalam Action akan menyelesaikannya juga,
     * tapi dengan harga yang tidak sepadan: Action di arsitektur ini
     * HTTP-agnostik dan hanya memuat aturan bisnis, dan setiap Action auth baru
     * harus ingat melakukannya. Menurunkannya dari respons memakai sumber yang
     * sama dengan yang dilihat klien, dan nol baris kode bisnis berubah.
     */
    private function recordAuthOutcome(Request $request, Response $response, int $status): void
    {
        if (! $this->logger->capturing('auth')) {
            return;
        }

        $route = $request->route()?->getName();

        /** @var array<string, array<string, string>> $map */
        $map = (array) $this->config->get('axiom.auth_routes', []);

        if ($route === null || ! isset($map[$route])) {
            return;
        }

        $event = $status < 400
            ? ($map[$route]['ok'] ?? null)
            : ($map[$route]['fail'] ?? null);

        if ($event === null) {
            return;
        }

        $this->logger->event($event, [
            'auth' => array_filter([
                'route' => $route,
                'status' => $status,
                'user_id' => $request->user()?->getAuthIdentifier(),

                // Kode galat mesin (`invalid_credentials`, `account_not_active`)
                // — inilah yang membedakan "kata sandi salah" dari "akun belum
                // aktif" saat menyelidiki lonjakan kegagalan login.
                'reason' => $this->errorPayload($response, $status)['code'] ?? null,

                ...$this->redactor->ip($request->ip()),
            ], static fn (mixed $v): bool => $v !== null),
        ], $status < 400 ? 'info' : 'warning');
    }

    /**
     * 401/403/422/429 dipancarkan lagi sebagai `security.*`.
     *
     * Bukan duplikasi tanpa guna: kelas event ini yang dipakai untuk alert dan
     * penyelidikan abuse, dan memisahkannya berarti kueri Axiom-nya tidak perlu
     * menyaring seluruh trafik HTTP hanya untuk menemukannya.
     */
    private function recordSecurityEvent(Request $request, int $status): void
    {
        if (! $this->logger->capturing('security')) {
            return;
        }

        $event = match ($status) {
            401 => 'security.unauthenticated',
            403 => 'security.forbidden',
            422 => 'security.validation_failed',
            429 => 'security.rate_limited',
            default => null,
        };

        if ($event === null) {
            return;
        }

        $this->logger->event($event, [
            'http' => array_filter([
                'method' => $request->getMethod(),
                'path' => '/'.ltrim($request->path(), '/'),
                'route' => $request->route()?->getName(),
                'status' => $status,
                ...$this->redactor->ip($request->ip()),
            ], static fn (mixed $v): bool => $v !== null),
        ], $status === 429 ? 'warning' : 'notice');
    }

    /**
     * Request gagal dan request lambat SELALU dikirim.
     *
     * Sampling yang ikut membuang error akan menghapus justru satu-satunya
     * bukti dari kejadian yang jarang — dan yang jarang itulah yang di-debug.
     */
    private function shouldSample(int $status, bool $slow): bool
    {
        if ($status >= 400 || $slow) {
            return true;
        }

        $rate = (float) $this->config->get(
            $status >= 300 ? 'axiom.sampling.redirect' : 'axiom.sampling.success',
            1.0,
        );

        if ($rate >= 1.0) {
            return true;
        }

        if ($rate <= 0.0) {
            return false;
        }

        return (mt_rand() / mt_getrandmax()) < $rate;
    }

    private function ignored(Request $request): bool
    {
        /** @var array<int, string> $patterns */
        $patterns = (array) $this->config->get('axiom.ignore_paths', []);

        return $patterns !== [] && Str::is($patterns, $request->path());
    }

    /** @return array<array-key, mixed>|null */
    private function query(Request $request): ?array
    {
        if ($this->payloadMode() === 'none') {
            return null;
        }

        $query = $request->query();

        return $query === [] ? null : $this->redactor->payload($query);
    }

    /** @return array<array-key, mixed>|string|null */
    private function body(Request $request): array|string|null
    {
        if ($this->payloadMode() === 'none' || $request->isMethod('GET')) {
            return null;
        }

        $raw = $request->getContent();
        $maxBytes = (int) $this->config->get('axiom.limits.max_string', 512) * 8;

        // Body raksasa hampir selalu berarti unggahan atau impor massal.
        // Ukurannya cukup untuk men-debug; isinya tidak sepadan risikonya.
        if (strlen($raw) > $maxBytes) {
            return '[bytes:'.strlen($raw).']';
        }

        $input = $request->isJson() ? (array) $request->json()->all() : $request->post();

        return $input === [] ? null : $this->redactor->payload($input);
    }

    /**
     * Berkas unggahan: ukuran dan tipe saja.
     *
     * Nama berkas aslinya sengaja tidak diambil — "KTP_Budi.jpg" atau
     * "selfie_nik_327....jpg" membocorkan identitas lewat metadata, dan yang
     * dibutuhkan saat men-debug unggahan gagal hanyalah ukuran dan MIME.
     *
     * @return array<string, array<string, mixed>>|null
     */
    private function files(Request $request): ?array
    {
        $files = $request->allFiles();

        if ($files === []) {
            return null;
        }

        $out = [];

        foreach ($files as $field => $file) {
            $file = is_array($file) ? ($file[0] ?? null) : $file;

            if (! $file instanceof UploadedFile) {
                continue;
            }

            $out[(string) $field] = [
                'bytes' => $file->getSize(),
                'mime' => $file->getClientMimeType(),
                'valid' => $file->isValid(),
            ];
        }

        return $out === [] ? null : $out;
    }

    /**
     * Isi respons galat: `{message, code, errors}`.
     *
     * Dibaca eksplisit per field, tidak lewat Redactor::payload(), karena
     * `code` di sini adalah kode galat mesin (`invalid_credentials`) yang
     * paling berguna saat debugging — sementara `code` di body REQUEST adalah
     * kode verifikasi enam angka, sebuah kredensial. Dua arti, satu nama;
     * jalurnya harus dipisah.
     *
     * @return array<string, mixed>|null
     */
    private function errorPayload(Response $response, int $status): ?array
    {
        if ($status < 400 || $this->config->get('axiom.privacy.response_payload') !== 'errors_only') {
            return null;
        }

        $content = $response->getContent();
        $contentType = (string) $response->headers->get('Content-Type', '');

        if ($content === false || $content === '' || ! str_contains($contentType, 'json')) {
            return null;
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return null;
        }

        $errors = $decoded['errors'] ?? null;

        return array_filter([
            'message' => isset($decoded['message']) && is_string($decoded['message'])
                ? $this->redactor->text($decoded['message'])
                : null,

            'code' => isset($decoded['code']) && is_scalar($decoded['code'])
                ? $this->redactor->text((string) $decoded['code'], 64)
                : null,

            // Nama field yang gagal validasi — itu yang menjelaskan penolakan.
            // Nilai yang dikirim pengguna tidak ikut.
            'fields' => is_array($errors) ? array_slice(array_keys($errors), 0, 25) : null,
        ], static fn (mixed $v): bool => $v !== null && $v !== []);
    }

    private function responseSize(Response $response): ?int
    {
        $length = $response->headers->get('Content-Length');

        if ($length !== null && is_numeric($length)) {
            return (int) $length;
        }

        $content = $response->getContent();

        return $content === false ? null : strlen($content);
    }

    private function levelFor(int $status): string
    {
        return match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default => 'info',
        };
    }

    private function payloadMode(): string
    {
        return (string) $this->config->get('axiom.privacy.request_payload', 'redacted');
    }
}
