<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\TaskStatus;

/**
 * Task yang sudah selesai/menggantung tidak bisa dibatalkan lagi.
 *
 * Sebelum ini `POST tasks/{task}/cancel` hanya dijaga Policy (poster/worker)
 * dan tidak memeriksa status sama sekali, jadi task yang sudah `completed`,
 * `expired`, `cancelled`, `refunded`, atau sedang `disputed` masih bisa
 * "dibatalkan" lewat API. Pada task yang sudah `completed` itu berarti
 * mengembalikan dana yang sudah dilepas ke pekerja — uang yang sudah menjadi
 * milik orang lain.
 */
final class TaskNotCancellableException extends DomainException
{
    private function __construct(
        string $message,
        private readonly TaskStatus $status,
        private readonly ?string $reason = null,
    ) {
        parent::__construct($message);
    }

    public static function becauseStatus(TaskStatus $status): self
    {
        return new self(
            "Task dengan status {$status->value} tidak bisa dibatalkan lagi.",
            $status,
        );
    }

    /** Masih ada sengketa mitra yang belum diputuskan pengelola. */
    public static function becauseOpenDispute(TaskStatus $status): self
    {
        return new self(
            'Task ini punya sengketa mitra yang belum diputuskan pengelola.',
            $status,
            'open_dispute',
        );
    }

    public function errorCode(): string
    {
        return 'task_not_cancellable';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return array_filter(
            ['status' => $this->status->value, 'reason' => $this->reason],
            static fn (?string $v): bool => $v !== null,
        );
    }
}
