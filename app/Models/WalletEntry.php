<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris buku besar. Append-only.
 *
 * `$timestamps = false` bukan penghematan: tabelnya memang tidak punya
 * `updated_at`, sama seperti `admin_audit_logs`. Sebuah baris uang yang bisa
 * disunting tidak membuktikan apa pun, dan kolom "kapan terakhir disunting"
 * adalah undangan untuk menyuntingnya.
 */
final class WalletEntry extends Model
{
    use HasUlid;

    public $timestamps = false;

    /**
     * Sengaja kosong.
     *
     * Baris buku besar hanya lahir dari `App\Support\WalletLedger`, yang
     * menyusun atributnya satu per satu. Tidak ada satu pun payload klien
     * yang boleh sampai ke tabel ini.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => WalletEntryType::class,
            'direction' => WalletEntryDirection::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /**
     * Keyset ordering. `id` wajib sebagai pemecah seri — beberapa baris bisa
     * lahir dalam satu transaksi dan berbagi `created_at` yang sama persis,
     * dan tanpa pemecah seri cursor bisa melewati atau mengulang baris.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
