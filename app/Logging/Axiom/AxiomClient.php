<?php

declare(strict_types=1);

namespace App\Logging\Axiom;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Log\LogManager;
use Throwable;

/**
 * Transport ke Axiom. Hanya HTTP — tanpa buffering, tanpa pembentukan event.
 *
 * Satu aturan yang mengatur seluruh kelas ini: KEGAGALAN PENGIRIMAN TIDAK
 * PERNAH BOLEH MENGGAGALKAN REQUEST. Observability adalah pengamat, bukan
 * ketergantungan. Karena itu setiap Throwable ditelan di sini dan dilaporkan
 * ke channel lokal — sebuah dataset yang penuh atau token yang kedaluwarsa
 * tidak boleh menjadi insiden 500 bagi pengguna.
 */
final class AxiomClient
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly Config $config,
        private readonly LogManager $log,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return bool true bila Axiom menerimanya
     */
    public function send(array $events): bool
    {
        if ($events === []) {
            return true;
        }

        $token = (string) $this->config->get('axiom.token', '');
        $dataset = (string) $this->config->get('axiom.dataset', '');

        if ($token === '' || $dataset === '') {
            $this->fallback('kredensial Axiom belum diisi', ['events' => count($events)]);

            return false;
        }

        try {
            $response = $this->http
                ->withToken($token)
                ->withHeaders($this->extraHeaders())
                ->timeout((float) $this->config->get('axiom.timeout', 3.0))
                ->connectTimeout((float) $this->config->get('axiom.connect_timeout', 1.5))
                ->retry(max(1, (int) $this->config->get('axiom.retries', 1)), 100, throw: false)
                ->asJson()
                ->post($this->ingestUrl($dataset), $events);
        } catch (Throwable $e) {
            // Jaringan mati, DNS gagal, TLS ditolak. Bukan urusan pengguna.
            $this->fallback('pengiriman ke Axiom gagal: '.$e->getMessage(), [
                'events' => count($events),
                'exception' => $e::class,
            ]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        // Body respons Axiom hanya menjelaskan penolakan (skema, kuota, token);
        // tetap dipotong karena tidak ada gunanya menulis satu halaman ke log
        // lokal, dan bisa memuat kembali cuplikan event yang kita kirim.
        $this->fallback('Axiom menolak batch', [
            'events' => count($events),
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 300),
        ]);

        return false;
    }

    private function ingestUrl(string $dataset): string
    {
        $base = rtrim((string) $this->config->get('axiom.endpoint', 'https://api.axiom.co'), '/');

        return $base.'/v1/datasets/'.rawurlencode($dataset).'/ingest';
    }

    /** @return array<string, string> */
    private function extraHeaders(): array
    {
        $orgId = (string) $this->config->get('axiom.org_id', '');

        // Hanya perlu untuk personal token; API token sudah terikat ke satu
        // organisasi sehingga header ini justru ditolak.
        return $orgId === '' ? [] : ['X-Axiom-Org-Id' => $orgId];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function fallback(string $message, array $context): void
    {
        $channel = (string) $this->config->get('axiom.fallback_channel', 'single');

        // Menulis kegagalan Axiom KE Axiom akan berputar tak berujung.
        if ($channel === 'axiom' || $channel === '') {
            $channel = 'single';
        }

        try {
            $this->log->channel($channel)->warning('[axiom] '.$message, $context);
        } catch (Throwable) {
            // Channel lokal pun gagal (disk penuh, izin berkas). Tidak ada
            // tempat lain untuk mengeluh; menelannya lebih baik daripada
            // menjatuhkan request yang sudah selesai dilayani.
        }
    }
}
