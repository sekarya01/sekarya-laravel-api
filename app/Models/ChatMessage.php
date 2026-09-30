<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatMessageType;
use App\Enums\ChatSystemEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Satu pesan. Dihapus pengirimnya = soft delete + isi dikosongkan (kerangka
 * tetap, supaya urutan & kutipan balasan tidak rusak). Room dinonaktifkan =
 * hapus permanen.
 */
final class ChatMessage extends Model
{
    use HasUlids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'room_id', 'sender_id', 'client_message_id', 'type', 'reply_type',
        'replied_message_id', 'attachment_id', 'caption', 'system_event', 'system_params',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'room_id' => 'integer',
            'sender_id' => 'integer',
            'type' => ChatMessageType::class,
            'reply_type' => ChatMessageType::class,
            'system_event' => ChatSystemEvent::class,
            'system_params' => 'array',
        ];
    }

    /** ULID huruf besar, sama dengan id publik lain di API ini. */
    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }

    /** @return BelongsTo<ChatRoom, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /** @return BelongsTo<ChatAttachment, $this> */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(ChatAttachment::class, 'attachment_id');
    }

    /** @return BelongsTo<self, $this> */
    public function replied(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replied_message_id')->withTrashed();
    }

    /**
     * Bentuk isi yang sebenarnya: untuk `reply` = `reply_type`.
     */
    public function bodyType(): ChatMessageType
    {
        return $this->type === ChatMessageType::Reply
            ? ($this->reply_type ?? ChatMessageType::Text)
            : $this->type;
    }

    /**
     * Keyset ordering — `id` (ULID, urut waktu) sebagai pemutus seri.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
