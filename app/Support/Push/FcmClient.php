<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Exceptions\Push\FcmException;
use App\Exceptions\Push\InvalidDeviceTokenException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Log\LogManager;
use JsonException;
use Throwable;

/**
 * Transport FCM HTTP v1 — tanpa pustaka pihak ketiga.
 *
 * FCM v1 tidak menerima "server key"; ia menuntut access token OAuth2 yang
 * ditandatangani kunci privat service account. Karena itu kelas ini melakukan
 * dua hal: menukar kredensial service account menjadi access token (di-cache
 * sampai hampir kedaluwarsa), lalu mengirim satu pesan ke satu token.
 *
 * Satu kelas, satu tanggung jawab: BERBICARA KE FCM. Pembentukan copy ada di
 * PushMessages; pemilihan perangkat dan penyapuan token mati ada di
 * FcmPushNotifier.
 *
 * Kredensial dibaca dari berkas JSON service account (config `firebase.credentials`).
 * Bila FCM tidak aktif atau berkasnya tidak ada, `isEnabled()` bernilai false
 * dan pemanggil melewatinya dengan tenang — pengembangan lokal tidak butuh
 * kredensial produksi.
 */
final class FcmClient
{
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * ID kanal notifikasi Android. HARUS sama dengan kanal yang dibuat
     * aplikasi; kalau tidak, Android memakai pengaturan bawaan dan
     * kustomisasi kanal (bunyi, kepentingan) hilang tanpa galat.
     */
    private const ANDROID_CHANNEL_ID = 'sekarya_default';

    /** @var array{project_id: string, client_email: string, private_key: string}|null */
    private ?array $account = null;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly Config $config,
        private readonly LogManager $log,
        private readonly CacheRepository $cache,
    ) {}

    /** FCM siap dipakai: diaktifkan DAN berkas kredensialnya ada. */
    public function isEnabled(): bool
    {
        if (! (bool) $this->config->get('firebase.enabled', false)) {
            return false;
        }

        $path = (string) $this->config->get('firebase.credentials', '');

        return $path !== '' && is_file($path);
    }

    /**
     * Kirim satu pesan ke satu token perangkat.
     *
     * @throws InvalidDeviceTokenException token tidak lagi terdaftar — buang.
     * @throws FcmException kegagalan lain — catat, coba lagi lain waktu.
     */
    public function send(string $deviceToken, PushMessage $message): void
    {
        if (! $this->isEnabled()) {
            // Tidak ada yang salah: lingkungan ini memang tidak mengirim push.
            $this->log->channel('single')->debug('[fcm] push dilewati: FCM tidak aktif.');

            return;
        }

        $account = $this->serviceAccount();
        $accessToken = $this->accessToken($account);
        $url = sprintf(
            'https://fcm.googleapis.com/v1/projects/%s/messages:send',
            rawurlencode($account['project_id']),
        );

        try {
            $response = $this->http
                ->withToken($accessToken)
                ->acceptJson()
                ->timeout((float) $this->config->get('firebase.timeout', 10.0))
                ->post($url, ['message' => $this->payload($deviceToken, $message)]);
        } catch (Throwable $e) {
            // Jaringan/DNS/TLS. Bukan salah token, jadi jangan disapu.
            throw FcmException::transportFailed(0, $e->getMessage());
        }

        if ($response->successful()) {
            return;
        }

        if ($response->status() === 404 || $this->isUnregistered($response->json())) {
            throw InvalidDeviceTokenException::make();
        }

        throw FcmException::transportFailed($response->status(), (string) $response->body());
    }

    /**
     * Service account, dibaca sekali per proses.
     *
     * @return array{project_id: string, client_email: string, private_key: string}
     */
    private function serviceAccount(): array
    {
        if ($this->account !== null) {
            return $this->account;
        }

        $path = (string) $this->config->get('firebase.credentials', '');

        if ($path === '' || ! is_file($path)) {
            throw FcmException::misconfigured('berkas kredensial tidak ditemukan.');
        }

        try {
            $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw FcmException::misconfigured('berkas kredensial bukan JSON yang sah.');
        }

        if (! is_array($decoded)) {
            throw FcmException::misconfigured('berkas kredensial tidak berbentuk objek.');
        }

        foreach (['project_id', 'client_email', 'private_key'] as $key) {
            if (! isset($decoded[$key]) || ! is_string($decoded[$key]) || $decoded[$key] === '') {
                throw FcmException::misconfigured("kunci `{$key}` tidak ada di berkas kredensial.");
            }
        }

        /** @var array{project_id: string, client_email: string, private_key: string} $decoded */
        return $this->account = $decoded;
    }

    /** @param array{project_id: string, client_email: string, private_key: string} $account */
    private function accessToken(array $account): string
    {
        $key = 'fcm:access_token:'.sha1($account['client_email']);

        $cached = $this->cache->get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        [$token, $ttl] = $this->fetchAccessToken($account);

        // Disimpan sedikit lebih pendek dari masa berlaku sungguhannya supaya
        // token tidak kedaluwarsa di tengah pengiriman.
        $this->cache->put($key, $token, now()->addSeconds(max(60, $ttl - 60)));

        return $token;
    }

    /**
     * @param  array{project_id: string, client_email: string, private_key: string}  $account
     * @return array{0: string, 1: int} access token dan masa berlaku (detik)
     */
    private function fetchAccessToken(array $account): array
    {
        $now = time();

        $assertion = $this->signJwt([
            'iss' => $account['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_ENDPOINT,
            'iat' => $now,
            'exp' => $now + 3600,
        ], $account['private_key']);

        try {
            $response = $this->http
                ->asForm()
                ->timeout((float) $this->config->get('firebase.timeout', 10.0))
                ->post(self::TOKEN_ENDPOINT, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);
        } catch (Throwable $e) {
            throw FcmException::authenticationFailed(0, $e->getMessage());
        }

        if (! $response->successful()) {
            throw FcmException::authenticationFailed($response->status(), (string) $response->body());
        }

        $data = $response->json();
        $token = is_array($data) ? ($data['access_token'] ?? null) : null;

        if (! is_string($token) || $token === '') {
            throw FcmException::authenticationFailed(
                $response->status(),
                'respons tidak memuat access_token.',
            );
        }

        $ttl = is_array($data) && isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;

        return [$token, $ttl];
    }

    /**
     * Assertion JWT RS256 — ditandatangani kunci privat service account.
     *
     * @param  array<string, mixed>  $claims
     */
    private function signJwt(array $claims, string $privateKeyPem): string
    {
        $header = self::base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $payload = self::base64Url((string) json_encode($claims));
        $signingInput = $header.'.'.$payload;

        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false) {
            throw FcmException::misconfigured('private_key tidak bisa dibaca sebagai kunci privat.');
        }

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw FcmException::misconfigured('gagal menandatangani assertion JWT.');
        }

        return $signingInput.'.'.self::base64Url($signature);
    }

    /** @return array<string, mixed> */
    private function payload(string $deviceToken, PushMessage $message): array
    {
        $payload = [
            'token' => $deviceToken,
            'notification' => [
                'title' => $message->title,
                'body' => $message->body,
            ],
            // `high`: ini peristiwa bisnis yang harus sampai segera, bukan
            // pembaruan yang boleh menunggu perangkat bangun.
            'android' => [
                'priority' => 'high',
                'notification' => ['channel_id' => self::ANDROID_CHANNEL_ID],
            ],
            'apns' => [
                'headers' => ['apns-priority' => '10'],
                'payload' => ['aps' => ['sound' => 'default']],
            ],
        ];

        if ($message->data !== []) {
            $payload['data'] = $message->data;
        }

        return $payload;
    }

    /**
     * Apakah FCM menyatakan token ini tidak lagi sah.
     *
     * Dua bentuk jawaban: `404 NOT_FOUND` untuk token yang tidak ada, dan
     * `INVALID_ARGUMENT` untuk token yang bentuknya rusak. Keduanya berarti
     * token yang sama tidak akan pernah berhasil — jadi keduanya disapu.
     */
    private function isUnregistered(mixed $body): bool
    {
        if (! is_array($body)) {
            return false;
        }

        $error = $body['error'] ?? null;
        if (! is_array($error)) {
            return false;
        }

        if (($error['status'] ?? null) === 'NOT_FOUND') {
            return true;
        }

        foreach ($error['details'] ?? [] as $detail) {
            if (is_array($detail)
                && in_array($detail['errorCode'] ?? null, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
                return true;
            }
        }

        return false;
    }

    private static function base64Url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
