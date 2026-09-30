<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis pesan. `reply` membawa bentuk isinya sendiri di `reply_type`
 * (text/image/video/audio/file); `system` ditulis server, tanpa pengirim.
 */
enum ChatMessageType: string
{
    case Text = 'text';
    case Reply = 'reply';
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case File = 'file';
    case System = 'system';

    /** Jenis yang membawa lampiran berkas. */
    public function isMedia(): bool
    {
        return in_array($this, [self::Image, self::Video, self::Audio, self::File], true);
    }

    /** Audio & dokumen tidak bercaption. */
    public function allowsCaption(): bool
    {
        return ! in_array($this, [self::Audio, self::File], true);
    }

    /** Nilai sah untuk `reply_type`. @return list<string> */
    public static function replyBodyValues(): array
    {
        return [self::Text->value, self::Image->value, self::Video->value, self::Audio->value, self::File->value];
    }

    /**
     * Jenis lampiran dari berkas yang sungguh diunggah.
     *
     * MIME dari isi berkas (`finfo`) diutamakan, KECUALI wadah MPEG-4/3GP
     * tanpa video: pesan suara `.m4a` Android terbaca `video/mp4` — tanpa
     * pengecualian ini setiap VN tercatat `video` dan ditolak `kind_mismatch`.
     */
    public static function forFile(string $mime, string $extension): self
    {
        $audioContainer = in_array(strtolower($extension), ['m4a', 'aac', 'mp3', 'ogg', 'opus', 'wav', 'amr'], true);

        if ($audioContainer && (str_starts_with($mime, 'video/') || $mime === 'application/octet-stream')) {
            return self::Audio;
        }

        return self::forMime($mime);
    }

    /** Jenis lampiran dari MIME saja. */
    public static function forMime(string $mime): self
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::Image,
            str_starts_with($mime, 'video/') => self::Video,
            str_starts_with($mime, 'audio/') => self::Audio,
            default => self::File,
        };
    }
}
