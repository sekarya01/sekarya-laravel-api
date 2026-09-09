<?php

declare(strict_types=1);

namespace App\Logging\Axiom;

use App\Jobs\ShipAxiomBatch;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Str;
use Throwable;

/**
 * Penampung dan pembentuk event. Satu instance per proses (singleton).
 *
 * Kenapa ditampung dan tidak dikirim satu per satu: sebuah request yang
 * menghasilkan dua puluh event akan membayar dua puluh kali RTT ke Axiom, dan
 * pengguna yang menunggu response-nya membayar semuanya. Di sini seluruh event
 * ditumpuk di memori lalu dikirim SEKALI pada `terminate`.
 *
 * Setiap event punya `request_id` yang sama, sehingga satu baris galat di Axiom
 * bisa langsung diperluas menjadi seluruh cerita request itu — query yang
 * lambat, panggilan keluar yang gagal, log aplikasi, semuanya.
 */
final class AxiomLogger
{
    /** @var array<int, array<string, mixed>> */
    private array $buffer = [];

    /** @var array<string, mixed> */
    private array $context = [];

    private ?string $requestId = null;

    /**
     * Waktu mulai request.
     *
     * Disimpan DI SINI, bukan di middleware, karena Laravel membuat instance
     * middleware BARU untuk memanggil `terminate()` — state apa pun yang
     * ditaruh di sana hilang di antara `handle()` dan `terminate()`. Gejalanya
     * tidak kentara: durasi terhitung sejak epoch (miliaran milidetik), setiap
     * request lolos ambang "lambat", dan karena request lambat tidak pernah
     * di-sample, sampling diam-diam berhenti bekerja.
     */
    private ?float $startedAt = null;

    /**
     * Penjaga masuk-ulang.
     *
     * Saat `flush()` berjalan ia memanggil HTTP client dan LogManager, dan
     * keduanya memancarkan event yang kelas ini juga dengarkan. Tanpa penjaga
     * ini satu flush menghasilkan event baru yang memicu flush berikutnya.
     */
    private bool $sending = false;

    private int $dropped = 0;

    public function __construct(
        private readonly AxiomClient $client,
        private readonly Redactor $redactor,
        private readonly Config $config,
    ) {}

    public function enabled(): bool
    {
        return (bool) $this->config->get('axiom.enabled', false);
    }

    public function capturing(string $group): bool
    {
        return $this->enabled() && (bool) $this->config->get('axiom.capture.'.$group, false);
    }

    public function redactor(): Redactor
    {
        return $this->redactor;
    }

    /**
     * Korelasi seumur request. Dipakai juga sebagai header respons
     * `X-Request-Id`, supaya sebuah laporan bug dari klien bisa dipetakan ke
     * satu baris di Axiom tanpa menebak dari jam.
     */
    public function requestId(): string
    {
        return $this->requestId ??= (string) Str::ulid();
    }

    public function setRequestId(string $id): void
    {
        $this->requestId = $id;
    }

    public function markStart(float $at): void
    {
        $this->startedAt = $at;
    }

    /** Milidetik sejak `markStart()`, atau null bila belum pernah ditandai. */
    public function elapsedMs(): ?int
    {
        return $this->startedAt === null
            ? null
            : (int) round((microtime(true) - $this->startedAt) * 1000);
    }

    /**
     * Kolom yang ikut di SETIAP event berikutnya (user, rute, dan sejenisnya).
     *
     * @param  array<string, mixed>  $fields
     */
    public function withContext(array $fields): void
    {
        $this->context = [...$this->context, ...array_filter(
            $fields,
            static fn (mixed $v): bool => $v !== null,
        )];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function event(string $event, array $fields = [], string $level = 'info'): void
    {
        if (! $this->enabled() || $this->sending) {
            return;
        }

        $max = (int) $this->config->get('axiom.max_batch', 500);

        if (count($this->buffer) >= $max) {
            // Membuang event lebih baik daripada menghabiskan memori proses.
            // Jumlah yang dibuang dilaporkan saat flush supaya lubangnya
            // terlihat di Axiom, bukan hilang tanpa jejak.
            $this->dropped++;

            return;
        }

        $envelope = [
            '_time' => now()->format('Y-m-d\TH:i:s.up'),
            'service' => $this->config->get('axiom.service'),
            'env' => $this->config->get('app.env'),
            'level' => $level,
            'kind' => Str::before($event, '.'),
            'event' => $event,
            'request_id' => $this->requestId(),
            ...$this->machineFields(),
            ...$this->context,
            ...$fields,
        ];

        try {
            // Jaring terakhir: pola nilai diperiksa lagi di seluruh event, jadi
            // listener yang lupa menyaring payload-nya tetap tidak bisa
            // membocorkan token atau alamat e-mail.
            $this->buffer[] = $this->redactor->scrubDeep($envelope);
        } catch (Throwable) {
            // Bahkan penyaringan pun tidak boleh menjatuhkan request. Kalau
            // sebuah nilai patologis membuatnya gagal, event itu dilewati —
            // mengirim yang belum tersaring bukan pilihan.
            $this->dropped++;
        }
    }

    /**
     * Dikirim sekali di akhir siklus hidup proses.
     *
     * Buffer dikosongkan SEBELUM pengiriman: kalau transport melempar, event
     * yang sama tidak boleh ikut terkirim ulang pada flush berikutnya dan
     * menggandakan dirinya.
     */
    public function flush(): void
    {
        // `dropped > 0` ikut diperiksa, bukan hanya buffer: kalau batasnya
        // terlampaui sampai SETIAP event terbuang, buffer-nya kosong — dan
        // tanpa syarat ini, kehilangan itu tidak pernah dilaporkan ke mana pun.
        if (($this->buffer === [] && $this->dropped === 0) || $this->sending) {
            return;
        }

        if ($this->dropped > 0) {
            $this->buffer[] = [
                '_time' => now()->format('Y-m-d\TH:i:s.up'),
                'service' => $this->config->get('axiom.service'),
                'env' => $this->config->get('app.env'),
                'level' => 'warning',
                'kind' => 'axiom',
                'event' => 'axiom.dropped',
                'request_id' => $this->requestId(),
                'dropped' => $this->dropped,
            ];
        }

        $batch = $this->buffer;
        $this->buffer = [];
        $this->dropped = 0;

        $this->sending = true;

        try {
            $this->dispatch($batch);
        } catch (Throwable) {
            // Sudah ditangani di dalam client; ini menutup kemungkinan
            // kegagalan pada jalur queue (mis. tabel `jobs` tidak terjangkau).
        } finally {
            $this->sending = false;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function pending(): array
    {
        return $this->buffer;
    }

    /** Dipakai test dan perintah artisan untuk membuang tumpukan. */
    public function discard(): void
    {
        $this->buffer = [];
        $this->dropped = 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     */
    private function dispatch(array $batch): void
    {
        if ($this->config->get('axiom.delivery') === 'queue') {
            ShipAxiomBatch::dispatch($batch)
                ->onQueue((string) $this->config->get('axiom.queue', 'default'));

            return;
        }

        $this->client->send($batch);
    }

    /**
     * Kolom mesin. `release` menjawab "versi mana yang error" — tanpa itu
     * sebuah lonjakan galat tidak bisa dikaitkan ke deploy yang menyebabkannya.
     *
     * @return array<string, mixed>
     */
    private function machineFields(): array
    {
        $release = (string) $this->config->get('axiom.release', '');
        $host = gethostname();

        return array_filter([
            'host' => $host === false ? null : $host,
            'release' => $release === '' ? null : $release,
        ], static fn (mixed $v): bool => $v !== null);
    }
}
