<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WalletEntryDirection;
use App\Models\Concerns\HasUlid;
use Database\Factories\WalletFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Saldo satu orang.
 *
 * `balance` TIDAK mass-assignable, dan itu aturan yang sederajat dengan
 * `users.status`: sebuah kolom yang menentukan berapa uang seseorang tidak
 * boleh bisa disetel dari larik atribut. Satu `Wallet::create($request->all())`
 * di jalur mana pun akan menjadi mesin cetak uang, dan kelalaian seperti itu
 * tidak menimbulkan galat apa pun.
 *
 * Yang boleh mengubahnya HANYA `App\Support\WalletLedger`, karena setiap
 * perubahan saldo wajib meninggalkan baris buku besar. Saldo yang berubah
 * tanpa baris penjelas adalah selisih yang tidak bisa ditelusuri siapa pun.
 */
final class Wallet extends Model
{
    /** @use HasFactory<WalletFactory> */
    use HasFactory, HasUlid;

    /** Sengaja TANPA `balance`. Lihat catatan kelas. */
    protected $fillable = ['user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'balance' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WalletEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(WalletEntry::class);
    }

    /**
     * Saldo yang DIHITUNG ULANG dari buku besar.
     *
     * Bukan jalur baca normal — `balance` yang di-cache ada justru supaya
     * pembacaan tidak tumbuh sebanding riwayat. Ini dipakai untuk
     * membuktikan cache-nya tidak melenceng: dipanggil test, dan tersedia
     * untuk penelusuran saat ada yang mempersoalkan angkanya.
     */
    public function recomputedBalance(): int
    {
        $credit = (int) $this->entries()
            ->where('direction', WalletEntryDirection::Credit)->sum('amount');
        $debit = (int) $this->entries()
            ->where('direction', WalletEntryDirection::Debit)->sum('amount');

        return $credit - $debit;
    }
}
