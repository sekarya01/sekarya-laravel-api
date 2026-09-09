<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Pemberi kerja menilai tanpa menyebut pekerja yang mana.
 *
 * Pada task satu orang, sasarannya jelas dan boleh dikosongkan. Begitu task
 * merekrut lebih dari satu orang, menebak sasaran berarti menaruh penilaian
 * pada orang yang salah — dan rating tidak bisa dicabut kembali.
 */
final class ReviewTargetRequiredException extends DomainException
{
    private function __construct(private readonly int $workerCount, string $message)
    {
        parent::__construct($message);
    }

    public static function amongWorkers(int $workerCount): self
    {
        return new self($workerCount, sprintf(
            'Pekerjaan ini punya %d pekerja. Sebutkan `worker_id` yang dinilai.',
            $workerCount,
        ));
    }

    public function errorCode(): string
    {
        return 'review_target_required';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['worker_count' => $this->workerCount];
    }
}
