<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Peristiwa pesan `system`. Nilainya KONTRAK: klien merender copy dari sini
 * (+ `system_params`), `caption` hanya cadangan untuk nilai yang belum dikenal.
 */
enum ChatSystemEvent: string
{
    case RoomCreated = 'room_created';
    case TaskCompleted = 'task_completed';
    case TaskCancelled = 'task_cancelled';
    case RoomExpired = 'room_expired';

    public static function forFinalStatus(TaskStatus $status): self
    {
        return match ($status) {
            TaskStatus::Completed => self::TaskCompleted,
            TaskStatus::Cancelled => self::TaskCancelled,
            default => self::RoomExpired,
        };
    }

    /** Teks cadangan (klien lama / event baru). */
    public function fallbackCaption(): string
    {
        return match ($this) {
            self::RoomCreated => 'Chat dibuka. Gunakan untuk koordinasi tugas ini.',
            self::TaskCompleted => 'Tugas selesai. Chat ditutup.',
            self::TaskCancelled => 'Tugas dibatalkan. Chat ditutup.',
            self::RoomExpired => 'Chat ditutup.',
        };
    }
}
