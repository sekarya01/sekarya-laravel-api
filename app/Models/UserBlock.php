<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Blokir antar pengguna (G7). Satu arah per baris; penyaringan memakai
 * keduanya (saling tidak terlihat).
 */
final class UserBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    /** @return BelongsTo<User, $this> */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    /** @return BelongsTo<User, $this> */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    /**
     * Semua id yang tidak boleh dilihat oleh `$userId` — yang ia blokir DAN
     * yang memblokirnya.
     *
     * Saling tidak terlihat adalah pilihan sadar: blokir satu arah membuat
     * orang yang diblokir tetap bisa mengintai lewat akun lain, tapi setidaknya
     * feed pihak yang memblokir bersih. Arah sebaliknya menutup celah "diblokir
     * tapi masih muncul di feed-nya".
     *
     * @return list<int>
     */
    public static function hiddenIdsFor(int $userId): array
    {
        $blocked = self::query()->where('blocker_id', $userId)->pluck('blocked_id');
        $blockers = self::query()->where('blocked_id', $userId)->pluck('blocker_id');

        return $blocked
            ->merge($blockers)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function existsBetween(int $a, int $b): bool
    {
        return self::query()
            ->where(function ($q) use ($a, $b): void {
                $q->where('blocker_id', $a)->where('blocked_id', $b);
            })
            ->orWhere(function ($q) use ($a, $b): void {
                $q->where('blocker_id', $b)->where('blocked_id', $a);
            })
            ->exists();
    }
}
