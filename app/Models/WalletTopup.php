<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletTopupStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\WalletTopupFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan isi saldo.
 *
 * `status` TIDAK mass-assignable, satu aturan dengan `users.status` dan
 * `admins.role`: kolom yang menentukan apakah uang jadi bertambah tidak boleh
 * datang dari larik atribut, dan bawaan kolomnya adalah keadaan yang paling
 * tidak menguntungkan pengirimnya (`awaiting_confirmation` — belum ada uang
 * yang dianggap masuk).
 *
 * @property-read WalletTopupStatus $status
 */
final class WalletTopup extends Model
{
    /** @use HasFactory<WalletTopupFactory> */
    use HasFactory, HasUlid;

    /** Sengaja TANPA `status`. */
    protected $fillable = ['user_id', 'amount', 'sender_note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => WalletTopupStatus::class,
            'amount' => 'integer',
            'reviewed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /**
     * Antrean pengelola: paling lama menunggu di depan.
     *
     * `created_at`, bukan `reported_at` seperti di `payments` — di sini
     * permintaannya ADALAH laporannya, jadi keduanya waktu yang sama dan
     * kolom kedua cuma akan jadi tempat kedua yang bisa melenceng.
     *
     * @param  Builder<$this>  $query
     */
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
