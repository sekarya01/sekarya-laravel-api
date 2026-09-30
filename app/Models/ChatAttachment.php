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
        'room_id', 'uploader_id', 'kind', 'path', 'file_name', 'extension',
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

    /** ULID huruf besar, sama dengan id publik lain di API ini. */
    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }
}
