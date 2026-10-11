<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatRoomStatus;
use App\Enums\ChatRoomType;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

/**
 * Satu room chat per task. Nama & foto room dibaca dari task-nya, tidak
 * disalin. `status` dan penanda waktunya TIDAK mass-assignable — yang
 * memindahkannya hanya `ChatRoomLifecycle` dan `DeactivateChatRoomAction`.
 */
final class ChatRoom extends Model
{
    use HasUlid, SoftDeletes;

    /** @var list<string> */
    protected $fillable = ['task_id', 'type', 'avatar'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'task_id' => 'integer',
            'type' => ChatRoomType::class,
            'status' => ChatRoomStatus::class,
            'expired_at' => 'datetime',
            'deactivated_at' => 'datetime',
        ];
    }

    /** Status bawaan tanpa lewat mass assignment. */
    protected $attributes = ['status' => 'active'];

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class)->withTrashed();
    }

    /** @return HasMany<ChatParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(ChatParticipant::class, 'room_id');
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'room_id');
    }

    /** @return BelongsTo<ChatMessage, $this> */
    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'last_message_id')->withTrashed();
    }

    /**
     * Room milik seseorang (peserta), di luar yang sudah dinonaktifkan.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where('chat_rooms.status', '!=', ChatRoomStatus::Deactivated->value)
            ->whereHas('participants', fn (Builder $q) => $q->where('user_id', $user->getKey()));
    }

    /**
     * `unread_count` untuk seorang peserta: pesan orang lain yang id-nya
     * melewati penanda bacanya. Pesan `system` (sender NULL) dan pesan yang
     * dihapus tidak dihitung.
     *
     * Penanda baca diambil SEKALI lewat join di kueri luar, lalu dibandingkan
     * di subquery hitungan — MySQL mendorongnya ke indeks sebagai kondisi,
     * sehingga yang dibaca hanya pesan SESUDAH penanda. Versi lama memakai
     * subquery per pesan: EXPLAIN ANALYZE (10 room × 3.000 pesan) membaca
     * 3.000 baris per room (14,5 ms) vs 10 baris (1,2 ms) dengan join ini.
     * LEFT join: tanpa baris peserta, semua pesan orang lain terhitung
     * (sama seperti COALESCE lama).
     *
     * @param  Builder<$this>  $query
     */
    public function scopeWithUnreadCountFor(Builder $query, User $user): void
    {
        $userId = $user->getKey();

        if ($query->getQuery()->columns === null) {
            $query->select('chat_rooms.*');
        }

        $query
            ->leftJoin('chat_participants as viewer_p', fn (JoinClause $join) => $join
                ->on('viewer_p.room_id', '=', 'chat_rooms.id')
                ->where('viewer_p.user_id', $userId))
            ->selectSub(fn (QueryBuilder $q) => $q
                ->from('chat_messages')
                ->selectRaw('count(*)')
                ->whereColumn('chat_messages.room_id', 'chat_rooms.id')
                ->whereNull('chat_messages.deleted_at')
                ->where('chat_messages.sender_id', '!=', $userId)
                ->whereRaw("chat_messages.id > COALESCE(viewer_p.last_read_message_id, '')"), 'unread_count');
    }

    /**
     * Semua yang dibaca ChatRoomResource untuk seorang penonton — relasi +
     * `unread_count`. Satu tempat, dipakai daftar maupun satu room.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForViewer(Builder $query, User $user): void
    {
        $query->with(['task', 'participants.user', 'lastMessage.attachment'])
            ->withUnreadCountFor($user);
    }

    /** Muat ulang room ini dalam bentuk siap tampil untuk `$user`. */
    public function freshFor(User $user): self
    {
        return self::query()->withTrashed()->whereKey($this->getKey())->forViewer($user)->firstOrFail();
    }

    /**
     * Urutan daftar room: aktivitas terakhir dulu (`updated_at` disentuh tiap
     * pesan), `id` sebagai pemutus seri keyset.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeRecentFirst(Builder $query): void
    {
        // Dikualifikasi: withUnreadCountFor menambah join ke chat_participants,
        // yang juga punya `updated_at` dan `id`.
        $query->orderByDesc('chat_rooms.updated_at')->orderByDesc('chat_rooms.id');
    }
}
