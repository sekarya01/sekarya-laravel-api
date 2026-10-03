<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatMessageType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Berkas chat hasil unggahan. Lahir SEBELUM pesannya (unggah dulu, lalu
 * kirim `attachment_id`); dihapus bersama berkasnya saat pesan dihapus atau
 * room dinonaktifkan.
 */
final class ChatAttachment extends Model
{
    use HasUlids;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = [
        'room_id', 'uploader_id', 'kind', 'path', 'thumbnail_path', 'file_name', 'extension',
        'mime_type', 'size', 'duration', 'width', 'height', 'waveform',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'room_id' => 'integer',
            'uploader_id' => 'integer',
            'kind' => ChatMessageType::class,
            'size' => 'integer',
            'duration' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'waveform' => 'array',
        ];
    }

    /**
     * Semua berkas milik lampiran ini di disk `public` (berkas + thumbnail
     * video bila ada) — dihapus bersama saat lampirannya dihapus.
     *
     * @return list<string>
     */
    public function storedPaths(): array
    {
        return array_values(array_filter([$this->path, $this->thumbnail_path]));
    }

    /**
     * Path pratinjau: foto = berkasnya sendiri, video = bingkai awal yang
     * diunggah perangkat (null untuk video lama), jenis lain tidak punya.
     */
    public function thumbnailPath(): ?string
    {
        return match ($this->kind) {
            ChatMessageType::Image => $this->path,
            ChatMessageType::Video => $this->thumbnail_path,
            default => null,
        };
    }

    /** ULID huruf besar, sama dengan id publik lain di API ini. */
    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }
}
