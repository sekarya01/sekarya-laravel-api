<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Logging\Axiom\AxiomClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mengirim satu batch event ke Axiom dari luar siklus request.
 *
 * Dipakai hanya bila `AXIOM_DELIVERY=queue`. Gunanya: request tidak menunggu
 * jaringan sama sekali — bahkan tiga detik timeout pun tidak ikut dibayar
 * pengguna. Harganya, log baru muncul di Axiom setelah worker mengambilnya,
 * jadi ini pilihan yang tepat hanya kalau latensi p99 lebih penting daripada
 * kesegeraan log.
 *
 * Payload-nya SUDAH tersaring saat masuk buffer, jadi tabel `jobs` tidak
 * pernah memuat data mentah.
 */
final class ShipAxiomBatch implements ShouldQueue
{
    use Queueable;

    /** Log yang gagal terkirim tidak layak menahan worker lama-lama. */
    public int $tries = 2;

    public int $timeout = 15;

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function __construct(private readonly array $events) {}

    public function handle(AxiomClient $client): void
    {
        $client->send($this->events);
    }
}
