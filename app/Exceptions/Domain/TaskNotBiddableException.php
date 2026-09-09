<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\TaskStatus;

final class TaskNotBiddableException extends DomainException
{
    private function __construct(string $message, private readonly TaskStatus $status)
    {
        parent::__construct($message);
    }

    public static function becauseStatus(TaskStatus $status): self
    {
        return new self("Task tidak lagi menerima penawaran (status: {$status->value}).", $status);
    }

    public static function biddingClosed(): self
    {
        return new self('Masa penawaran task ini sudah ditutup.', TaskStatus::Open);
    }

    public function errorCode(): string
    {
        return 'task_not_biddable';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['status' => $this->status->value];
    }
}
