<?php

declare(strict_types=1);

namespace App\Logging\Axiom;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Jembatan Monolog -> Axiom.
 *
 * Dengan handler ini, setiap `Log::info()` / `Log::error()` yang sudah ada di
 * aplikasi ikut mendarat di Axiom tanpa satu pun baris kode aplikasi berubah,
 * dan membawa `request_id` yang sama dengan event HTTP-nya — jadi sebuah log
 * aplikasi bisa langsung dilihat dalam konteks request yang memancarkannya.
 *
 * Konteks log adalah tempat kebocoran paling sering terjadi
 * (`Log::info('gagal', ['user' => $user])` mengirim seluruh model, termasuk
 * hash kata sandi), jadi ia dilewatkan Redactor::payload() dengan aturan nama
 * kunci penuh — bukan hanya penyaring pola.
 */
final class AxiomHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly AxiomLogger $logger,
        private readonly Redactor $redactor,
        int|string|Level $level = Level::Debug,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        if (! $this->logger->capturing('logs')) {
            return;
        }

        $this->logger->event(
            'log.record',
            array_filter([
                'log' => array_filter([
                    'channel' => $record->channel,
                    'message' => $this->redactor->text($record->message),
                    'context' => $record->context === []
                        ? null
                        : $this->redactor->payload($record->context),
                    'extra' => $record->extra === []
                        ? null
                        : $this->redactor->payload($record->extra),
                ], static fn (mixed $v): bool => $v !== null),
            ]),
            strtolower($record->level->getName()),
        );
    }
}
