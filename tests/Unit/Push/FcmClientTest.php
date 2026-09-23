<?php

declare(strict_types=1);

namespace Tests\Unit\Push;

use App\Exceptions\Push\FcmException;
use App\Exceptions\Push\InvalidDeviceTokenException;
use App\Support\Push\FcmClient;
use App\Support\Push\PushMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Transport FCM HTTP v1 tanpa jaringan sungguhan.
 *
 * Yang dijaga di sini adalah bagian yang paling gampang rusak diam-diam:
 * bentuk assertion JWT, pemakaian ulang access token (cache), dan bentuk
 * payload yang dibaca perangkat. Semuanya lewat `Http::fake`.
 */
final class FcmClientTest extends TestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    /** Konfigurasi Firebase dengan kunci RSA sungguhan yang dibuat saat test. */
    private function configureFirebase(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        $this->assertNotFalse($resource, 'gagal membuat kunci RSA untuk test');

        openssl_pkey_export($resource, $pem);
        $this->assertIsString($pem);

        $path = tempnam(sys_get_temp_dir(), 'fcm-test-');
        $this->assertIsString($path);
        $this->tempFiles[] = $path;

        file_put_contents($path, json_encode([
            'project_id' => 'sekarya-test',
            'client_email' => 'svc@sekarya-test.iam.gserviceaccount.com',
            'private_key' => $pem,
        ]));

        config()->set('firebase.enabled', true);
        config()->set('firebase.credentials', $path);
    }

    private function message(): PushMessage
    {
        return new PushMessage(
            title: 'Penawaran baru',
            body: 'Budi menawar "Servis AC" sebesar Rp120.000.',
            data: ['type' => 'bid_placed', 'task_id' => '01JABC'],
        );
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'fcm.googleapis.com/*' => Http::response([
                'name' => 'projects/sekarya-test/messages/1',
            ]),
        ]);
    }

    public function test_it_exchanges_credentials_and_sends_the_message(): void
    {
        $this->configureFirebase();
        $this->fakeGoogle();

        app(FcmClient::class)->send('device-token-1', $this->message());

        // 1) Assertion JWT ditukar menjadi access token.
        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://oauth2.googleapis.com/token') {
                return false;
            }

            $data = $request->data();

            if (($data['grant_type'] ?? null) !== 'urn:ietf:params:oauth:grant-type:jwt-bearer') {
                return false;
            }

            $segments = explode('.', (string) ($data['assertion'] ?? ''));

            if (count($segments) !== 3) {
                return false;
            }

            $claims = json_decode((string) base64_decode(strtr($segments[1], '-_', '+/'), false), true);

            return is_array($claims)
                && ($claims['iss'] ?? null) === 'svc@sekarya-test.iam.gserviceaccount.com'
                && ($claims['aud'] ?? null) === 'https://oauth2.googleapis.com/token'
                && ($claims['scope'] ?? null) === 'https://www.googleapis.com/auth/firebase.messaging';
        });

        // 2) Pesan dikirim ke project yang benar, memakai access token, dengan
        //    bentuk payload yang diharapkan perangkat.
        Http::assertSent(function (Request $request): bool {
            if (! str_starts_with($request->url(), 'https://fcm.googleapis.com/v1/projects/sekarya-test/messages:send')) {
                return false;
            }

            if ($request->header('Authorization')[0] !== 'Bearer ya29.test-token') {
                return false;
            }

            $message = $request->data()['message'] ?? [];

            return ($message['token'] ?? null) === 'device-token-1'
                && ($message['notification']['title'] ?? null) === 'Penawaran baru'
                && ($message['data']['type'] ?? null) === 'bid_placed'
                && ($message['data']['task_id'] ?? null) === '01JABC'
                && ($message['android']['notification']['channel_id'] ?? null) === 'sekarya_notification_task';
        });
    }

    public function test_the_access_token_is_reused_across_sends(): void
    {
        $this->configureFirebase();
        $this->fakeGoogle();

        $client = app(FcmClient::class);
        $client->send('device-token-1', $this->message());
        $client->send('device-token-2', $this->message());

        $tokenCalls = 0;
        Http::assertSent(function (Request $request) use (&$tokenCalls): bool {
            if ($request->url() === 'https://oauth2.googleapis.com/token') {
                $tokenCalls++;
            }

            return true;
        });

        $this->assertSame(1, $tokenCalls, 'access token harus di-cache, bukan diambil ulang tiap kirim');
    }

    public function test_a_not_found_token_is_reported_as_invalid(): void
    {
        $this->configureFirebase();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/*' => Http::response([
                'error' => ['status' => 'NOT_FOUND'],
            ], 404),
        ]);

        $this->expectException(InvalidDeviceTokenException::class);

        app(FcmClient::class)->send('device-token-dead', $this->message());
    }

    public function test_an_unregistered_error_code_is_reported_as_invalid(): void
    {
        $this->configureFirebase();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'status' => 'INVALID_ARGUMENT',
                    'details' => [['errorCode' => 'UNREGISTERED']],
                ],
            ], 400),
        ]);

        $this->expectException(InvalidDeviceTokenException::class);

        app(FcmClient::class)->send('device-token-dead', $this->message());
    }

    public function test_a_server_error_is_not_treated_as_an_invalid_token(): void
    {
        $this->configureFirebase();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'ya29.test-token',
                'expires_in' => 3600,
            ]),
            'fcm.googleapis.com/*' => Http::response(['error' => 'boom'], 503),
        ]);

        // 503 bukan token mati: token-nya JANGAN disapu, cukup dicatat.
        $this->expectException(FcmException::class);
        $this->expectExceptionMessageMatches('/bukan|menolak/i');

        app(FcmClient::class)->send('device-token-alive', $this->message());
    }

    public function test_nothing_is_sent_when_firebase_is_disabled(): void
    {
        config()->set('firebase.enabled', false);
        config()->set('firebase.credentials', null);
        Http::fake();

        app(FcmClient::class)->send('device-token-1', $this->message());

        Http::assertNothingSent();
    }
}
