<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Satu notifikasi di kotak masuk in-app seseorang.
 *
 * Yang MENULIS baris ini hanya `App\Support\Push\PushDispatcher` — jalur yang
 * sama dengan push, supaya lonceng dan notifikasi ponsel tidak pernah berbeda
 * isi. Yang boleh berubah sesudahnya hanya `read_at`.
 */
final class UserNotification extends Model
{
    use HasUlids;

    /** Tidak ada `updated_at`: satu-satunya perubahan dicatat `read_at`. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['user_id', 'type', 'title', 'body', 'data'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * ULID huruf besar, sama dengan id publik lain di API ini (HasUlid).
     * Bawaan Laravel menghasilkan huruf kecil.
     */
    public function newUniqueId(): string
    {
        return (string) Str::ulid();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    /** @param Builder<$this> $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}
