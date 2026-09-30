<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatParticipantType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keanggotaan seseorang di satu room + penanda terima/baca miliknya.
 *
 * Penanda disimpan sebagai id pesan (ULID, urut waktu): status sebuah pesan
 * dihitung dari penanda peserta lain, tidak disimpan per pesan.
 */
final class ChatParticipant extends Model
{
    /** @var list<string> */
    protected $fillable = ['room_id', 'user_id', 'type', 'joined_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // Kolom pemilik dicast — lihat OwnerAuthorizationTypeTest.
            'room_id' => 'integer',
            'user_id' => 'integer',
            'type' => ChatParticipantType::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'last_read_at' => 'datetime',
            'muted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ChatRoom, $this> */
    public function room(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class, 'room_id');
    }

    /** Akun yang dihapus (anonim) tetap tampil di riwayat. @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }

    /** Nama tampilan sesuai perannya di task ini (mitra memakai nama pekerjanya). */
    public function displayName(): string
    {
        $user = $this->user;

        return $this->type === ChatParticipantType::Worker
            ? (string) $user->workerProfileOrNew()->resolvedName()
            : (string) $user->name;
    }

    public function avatarPath(): ?string
    {
        $user = $this->user;

        return $this->type === ChatParticipantType::Worker
            ? $user->workerProfileOrNew()->resolvedAvatarPath()
            : $user->avatar_path;
    }
}
