<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkerInviteCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kode undangan mitra pekerja — SATU-SATUNYA tempat aturan kedaluwarsa dibaca.
 *
 * Klien (mobile) hanya mengirim kode plain 8 char; server meng-hash lalu
 * mencari barisnya. Tiga penutup pintu, cek berurutan supaya pesannya tepat:
 * nonaktif → kedaluwarsa tanggal → kuota habis.
 */
class WorkerInviteCode extends Model
{
    /** @use HasFactory<WorkerInviteCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'code_hash', 'prefix', 'max_uses', 'used_count',
        'expires_at', 'is_active', 'note', 'created_by_admin_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Admin, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    /** @return HasMany<WorkerInviteRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(WorkerInviteRedemption::class, 'invite_code_id');
    }

    /** Hash deterministik untuk lookup: sha256 lowercase hex. */
    public static function hash(string $plain): string
    {
        return hash('sha256', mb_strtolower(trim($plain)));
    }

    public function isDateExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->used_count >= $this->max_uses;
    }

    /** Masih bisa dipakai: aktif DAN tanggal belum lewat DAN kuota tersisa. */
    public function isUsable(): bool
    {
        return $this->is_active && ! $this->isDateExpired() && ! $this->isExhausted();
    }

    public function remainingUses(): int
    {
        return max(0, $this->max_uses - $this->used_count);
    }
}
