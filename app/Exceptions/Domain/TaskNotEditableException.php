<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\TaskStatus;

/**
 * Isi task hanya boleh berubah selama belum ada yang terikat padanya.
 *
 * Sejak pelamar pertama diterima, judul, harga, dan jadwal bukan lagi draf
 * milik pemberi kerja sendiri — itu kesepakatan yang dipakai orang lain untuk
 * memutuskan. Mengubahnya sepihak setelah itu memindahkan risiko ke pekerja.
 */
final class TaskNotEditableException extends DomainException
{
    private function __construct(string $message, private readonly TaskStatus $status)
    {
        parent::__construct($message);
    }

    public static function becauseStatus(TaskStatus $status): self
    {
        return new self(
            "Task dengan status {$status->value} tidak bisa diubah lagi.",
            $status,
        );
    }

    public function errorCode(): string
    {
        return 'task_not_editable';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['status' => $this->status->value];
    }
}
