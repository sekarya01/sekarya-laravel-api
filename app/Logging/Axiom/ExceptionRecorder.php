<?php

declare(strict_types=1);

namespace App\Logging\Axiom;

use App\Exceptions\Domain\DomainException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Mengubah sebuah Throwable menjadi event Axiom.
 *
 * Dipisah dari bootstrap/app.php supaya bisa diuji langsung — dan karena
 * bootstrap/app.php seharusnya hanya menyambungkan, bukan memuat logika.
 *
 * Pelanggaran aturan bisnis DIBEDAKAN dari galat sungguhan. Kata sandi salah
 * dan penawaran di bawah harga minimum adalah jalur normal aplikasi ini; kalau
 * ikut dikirim sebagai `error` mereka akan menenggelamkan galat sungguhan dan
 * membuat setiap alert berbasis "jumlah error" tidak ada artinya. Mereka tetap
 * dikirim — sebagai `error.domain` level notice — karena lonjakannya justru
 * sinyal yang berguna.
 */
final class ExceptionRecorder
{
    public function __construct(
        private readonly AxiomLogger $logger,
        private readonly Redactor $redactor,
    ) {}

    public function record(Throwable $e): void
    {
        if (! $this->logger->capturing('exceptions')) {
            return;
        }

        [$event, $level] = $this->classify($e);

        $this->logger->event($event, ['error' => $this->describe($e)], $level);
    }

    /**
     * Galat fatal PHP (kehabisan memori, batas waktu eksekusi).
     *
     * Jalur ini ada karena galat seperti itu tidak selalu sampai ke penangan
     * exception Laravel — proses bisa mati di tengah. Tanpa hook shutdown,
     * seluruh buffer event request itu hilang, termasuk jejak yang menjelaskan
     * apa yang sedang dikerjakan saat memori habis.
     *
     * @param  array{type: int, message: string, file: string, line: int}  $error
     */
    public function recordFatal(array $error): void
    {
        if (! $this->logger->capturing('exceptions')) {
            return;
        }

        $this->logger->event('error.fatal', [
            'error' => [
                'type' => 'php.fatal:'.$error['type'],
                'message' => $this->redactor->text($error['message']),
                'file' => $this->relative($error['file']),
                'line' => $error['line'],
            ],
        ], 'critical');
    }

    /** @return array{0: string, 1: string} */
    private function classify(Throwable $e): array
    {
        if ($e instanceof DomainException) {
            return ['error.domain', 'notice'];
        }

        // 404 / 405 / 419: kesalahan klien, bukan kerusakan server.
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return ['error.http', 'warning'];
        }

        return ['error.exception', 'error'];
    }

    /** @return array<string, mixed> */
    private function describe(Throwable $e): array
    {
        $fields = [
            'type' => $e::class,

            // Pesan exception adalah tempat rahasia paling sering bocor tanpa
            // sengaja — galat unique constraint MySQL memuat nilai yang bentrok,
            // dan pada tabel `users` nilai itu adalah alamat e-mail.
            'message' => $this->redactor->text($e->getMessage()),

            'file' => $this->relative($e->getFile()),
            'line' => $e->getLine(),
            'trace' => $this->redactor->trace($e->getTrace()),
        ];

        if ($e instanceof DomainException) {
            $fields['domain_code'] = $e->errorCode();
            $fields['http_status'] = $e->httpStatus();

            $context = $e->context();

            if ($context !== []) {
                $fields['context'] = $this->redactor->payload($context);
            }
        }

        if ($e instanceof HttpExceptionInterface) {
            $fields['http_status'] = $e->getStatusCode();
        }

        $previous = $e->getPrevious();

        if ($previous !== null) {
            // Penyebab aslinya yang biasanya menjelaskan galat: sebuah
            // QueryException yang dibungkus jadi galat generik tidak berguna
            // tanpa isi pembungkusnya.
            $fields['previous'] = [
                'type' => $previous::class,
                'message' => $this->redactor->text($previous->getMessage()),
                'file' => $this->relative($previous->getFile()),
                'line' => $previous->getLine(),
            ];
        }

        return $fields;
    }

    /**
     * Path absolut membocorkan struktur direktori server dan sering juga nama
     * pengguna sistem. Relatif juga jauh lebih mudah dibaca di Axiom.
     */
    private function relative(string $path): string
    {
        $base = base_path();

        return str_starts_with($path, $base)
            ? ltrim(substr($path, strlen($base)), '/')
            : $path;
    }
}
