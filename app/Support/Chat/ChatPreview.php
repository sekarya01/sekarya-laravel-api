<?php

declare(strict_types=1);

namespace App\Support\Chat;

use App\Enums\ChatMessageType;
use App\Models\ChatMessage;

/**
 * Teks ringkas sebuah pesan — `last_message.preview` di daftar room dan isi
 * notifikasi push. Satu tempat supaya keduanya tidak pernah berbeda kata.
 */
final class ChatPreview
{
    public static function of(ChatMessage $message): string
    {
        if ($message->trashed()) {
            return 'Pesan dihapus';
        }

        $caption = trim((string) $message->caption);

        $label = match ($message->bodyType()) {
            ChatMessageType::Image => 'Foto',
            ChatMessageType::Video => 'Video',
            ChatMessageType::Audio => 'Pesan suara',
            ChatMessageType::File => $message->attachment->file_name ?? 'Dokumen',
            default => null,
        };

        if ($label === null) {
            return mb_strimwidth($caption, 0, 120, '…');
        }

        return $caption === '' ? $label : mb_strimwidth($label.' · '.$caption, 0, 120, '…');
    }
}
