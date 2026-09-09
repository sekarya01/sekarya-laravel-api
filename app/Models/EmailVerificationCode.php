<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EmailVerificationCode extends Model
{
    protected $fillable = [
        'user_id', 'code_hash', 'attempts', 'expires_at', 'last_sent_at', 'request_ip',
    ];

    /** Hash kode tidak boleh ikut serialisasi apa pun. */
    protected $hidden = ['code_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function attemptsExhausted(): bool
    {
        return $this->attempts >= (int) config('sekarya.verification.max_attempts');
    }

    /** Masih bisa dipakai: belum dipakai, belum kedaluwarsa, percobaan belum habis. */
    public function isUsable(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired() && ! $this->attemptsExhausted();
    }

    public function secondsUntilResendAllowed(): int
    {
        if ($this->last_sent_at === null) {
            return 0;
        }

        $cooldown = (int) config('sekarya.verification.resend_cooldown_seconds');
        $elapsed = $this->last_sent_at->diffInSeconds(now());

        return max(0, $cooldown - (int) $elapsed);
    }

    /** @param Builder<$this> $query */
    public function scopeUsable(Builder $query): void
    {
        $query->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', (int) config('sekarya.verification.max_attempts'));
    }
}
