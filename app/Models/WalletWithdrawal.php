<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletWithdrawalStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\WalletWithdrawalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan penarikan saldo ke rekening.
 *
 * Rekeningnya DIRUJUK lewat `verification_id`, tidak disalin ke sini —
 * penjelasannya di migrasi. Konsekuensi yang harus diingat saat membaca kode
 * ini: nomor rekening TIDAK pernah ada di tabel ini, jadi Resource-nya tidak
 * punya apa pun untuk dibocorkan. Pengelola membacanya dari
 * `GET /admin/verifications/{verification}`, yang mencatat pembacaannya.
 *
 * @property-read WalletWithdrawalStatus $status
 */
final class WalletWithdrawal extends Model
{
    /** @use HasFactory<WalletWithdrawalFactory> */
    use HasFactory, HasUlid;

    /** Sengaja TANPA `status`. */
    protected $fillable = ['user_id', 'amount', 'verification_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => WalletWithdrawalStatus::class,
            'amount' => 'integer',
            'processed_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Rekening tujuan — baris verifikasi rekening yang sudah disetujui.
     *
     * @return BelongsTo<UserVerification, $this>
     */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(UserVerification::class, 'verification_id');
    }

    /** @return BelongsTo<Admin, $this> */
    public function processor(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'processed_by');
    }

    /** @param  Builder<$this>  $query */
    public function scopeQueueOrder(Builder $query): void
    {
        $query->orderBy('created_at')->orderBy('id');
    }

    /** @param  Builder<$this>  $query */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
