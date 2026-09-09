<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\Axiom\AxiomClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Transport. Satu aturan yang mengatur seluruh kelas ini, dan sebagian besar
 * test di sini menegaskannya: KEGAGALAN PENGIRIMAN TIDAK PERNAH BOLEH
 * MENGGAGALKAN REQUEST.
 */
final class AxiomClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('axiom.token', 'rahasia-token-uji');
        config()->set('axiom.dataset', 'sekarya-uji');
        config()->set('axiom.endpoint', 'https://api.axiom.co');
        config()->set('axiom.org_id', null);
        config()->set('axiom.retries', 1);
    }

    /** @return array<int, array<string, mixed>> */
    private function events(): array
    {
        return [['event' => 'axiom.ping', '_time' => '2026-09-08T00:00:00.000000Z']];
    }

    public function test_it_posts_the_batch_to_the_dataset_ingest_endpoint(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        $this->assertTrue(app(AxiomClient::class)->send($this->events()));

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.axiom.co/v1/datasets/sekarya-uji/ingest'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer rahasia-token-uji')
                && $request->data()[0]['event'] === 'axiom.ping';
        });
    }

    /** Dataset dengan spasi atau garis miring tidak boleh merusak URL-nya. */
    public function test_the_dataset_name_is_url_encoded(): void
    {
        config()->set('axiom.dataset', 'sekarya prod/1');
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        app(AxiomClient::class)->send($this->events());

        Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'sekarya%20prod%2F1/ingest'));
    }

    /** Header org hanya perlu untuk personal token; API token justru menolaknya. */
    public function test_the_org_header_is_only_sent_when_configured(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 200)]);

        app(AxiomClient::class)->send($this->events());
        Http::assertSent(fn (Request $r): bool => ! $r->hasHeader('X-Axiom-Org-Id'));

        config()->set('axiom.org_id', 'org-123');
        app(AxiomClient::class)->send($this->events());

        Http::assertSent(fn (Request $r): bool => $r->hasHeader('X-Axiom-Org-Id', 'org-123'));
    }

    public function test_an_empty_batch_is_a_no_op_and_touches_no_network(): void
    {
        Http::fake();

        $this->assertTrue(app(AxiomClient::class)->send([]));

        Http::assertNothingSent();
    }

    public function test_it_refuses_to_send_without_credentials_and_says_so_locally(): void
    {
        config()->set('axiom.token', '');
        Http::fake();

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'kredensial'));

        $this->assertFalse(app(AxiomClient::class)->send($this->events()));

        Http::assertNothingSent();
    }

    /**
     * Token kedaluwarsa atau kuota habis: Axiom menolak dengan 4xx. Itu bukan
     * insiden bagi pengguna, jadi tidak boleh melempar.
     */
    public function test_a_rejected_batch_is_reported_locally_and_does_not_throw(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('unauthorized', 401)]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'menolak')
                && $context['status'] === 401);

        $this->assertFalse(app(AxiomClient::class)->send($this->events()));
    }

    /** Jaringan mati / DNS gagal / TLS ditolak — sama saja: ditelan. */
    public function test_a_transport_exception_is_swallowed(): void
    {
        Http::fake(fn () => throw new \RuntimeException('getaddrinfo gagal'));

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once()
            ->withArgs(fn (string $message): bool => str_contains($message, 'gagal'));

        $this->assertFalse(app(AxiomClient::class)->send($this->events()));
    }

    /**
     * Menulis kegagalan Axiom KE Axiom akan berputar tak berujung, jadi
     * channel fallback yang salah konfigurasi harus dipaksa ke `single`.
     */
    public function test_the_fallback_channel_can_never_be_axiom_itself(): void
    {
        config()->set('axiom.fallback_channel', 'axiom');
        Http::fake(['api.axiom.co/*' => Http::response('', 500)]);

        Log::shouldReceive('channel')->once()->with('single')->andReturnSelf();
        Log::shouldReceive('warning')->once();

        $this->assertFalse(app(AxiomClient::class)->send($this->events()));
    }

    /**
     * Kalau channel lokal pun gagal (disk penuh, izin berkas), tidak ada
     * tempat lain untuk mengeluh — dan request tetap tidak boleh jatuh.
     */
    public function test_it_survives_the_local_log_channel_failing_too(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response('', 500)]);

        Log::shouldReceive('channel')->andThrow(new \RuntimeException('disk penuh'));

        $this->assertFalse(app(AxiomClient::class)->send($this->events()));
    }

    /** Body respons dipotong: tidak ada gunanya menulis satu halaman ke log lokal. */
    public function test_the_rejection_body_is_truncated_before_it_reaches_the_local_log(): void
    {
        Http::fake(['api.axiom.co/*' => Http::response(str_repeat('x', 5000), 400)]);

        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('warning')->once()
            ->withArgs(fn (string $m, array $context): bool => strlen((string) $context['body']) <= 300);

        app(AxiomClient::class)->send($this->events());
    }
}
