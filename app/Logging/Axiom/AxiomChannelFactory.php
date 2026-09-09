<?php

declare(strict_types=1);

namespace App\Logging\Axiom;

use Monolog\Level;
use Monolog\Logger;

/**
 * Pabrik channel `axiom` untuk config/logging.php (`driver => custom`).
 *
 * AxiomLogger dan Redactor diambil dari container — bukan dibuat baru — supaya
 * handler ini memakai buffer yang SAMA dengan middleware dan seluruh listener.
 * Kalau dibuat baru di sini, log aplikasi akan terkirim sebagai batch terpisah
 * dan kehilangan `request_id` yang menghubungkannya ke request.
 */
final class AxiomChannelFactory
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __invoke(array $config): Logger
    {
        $level = Level::fromName((string) ($config['level'] ?? 'debug'));

        $handler = new AxiomHandler(
            app(AxiomLogger::class),
            app(Redactor::class),
            $level,
        );

        return new Logger('axiom', [$handler]);
    }
}
