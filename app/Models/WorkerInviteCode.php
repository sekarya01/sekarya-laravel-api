<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WorkerInviteCodeFactory;
use Illuminate\Database\Eloquent\Builder;
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
        'code_hash', 'code_plain', 'prefix', 'max_uses', 'used_count',
        'expires_at', 'is_active', 'note', 'created_by_admin_id',
        'city', 'province',
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

    /**
     * Kode untuk ditampilkan ke pengelola — plain kalau ada, kalau tidak
     * (baris lama sebelum kolomnya ada) jatuh ke prefix bertopeng.
     */
    public function displayCode(): string
    {
        return $this->code_plain ?? ($this->prefix.'······');
    }

    /** Plain-nya memang tidak tersimpan (baris lama) — hanya topengnya. */
    public function isArchived(): bool
    {
        return $this->code_plain === null;
    }

    /**
     * Kode yang HIDUP untuk suatu wilayah pada saat ini: aktif, kuota
     * tersisa, tanggal belum lewat, dan cakupannya cocok — NULL berarti
     * nasional (berlaku di mana saja).
     *
     * Perbandingan case-insensitive: "bandung" dan "Bandung" adalah kota
     * yang sama, dan ejaan pengelola tidak bisa diasumsikan rapi.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $query
            ->where('is_active', true)
            ->whereColumn('used_count', '<', 'max_uses')
            ->where(fn (Builder $w) => $w
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()));
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeForArea(Builder $query, string $city, string $province): void
    {
        $city = mb_strtolower(trim($city));
        $province = mb_strtolower(trim($province));

        $query
            ->where(fn (Builder $w) => $w
                ->whereNull('city')
                ->orWhereRaw('LOWER(city) = ?', [$city]))
            ->where(fn (Builder $w) => $w
                ->whereNull('province')
                ->orWhereRaw('LOWER(province) = ?', [$province]));
    }

    /** Label cakupan untuk daftar: "Bandung, Jawa Barat" atau "Nasional". */
    public function areaLabel(): string
    {
        if ($this->city === null && $this->province === null) {
            return 'Nasional';
        }

        return trim(($this->city ?? '').(($this->city !== null && $this->province !== null) ? ', ' : '').($this->province ?? ''));
    }

    /**
     * Hash deterministik untuk lookup: sha256 hex dari kode apa adanya
     * (hanya trim tepi).
     *
     * SENGAJA case-sensitive: huruf kecil dan KAPITAL adalah dua simbol
     * berbeda dalam syarat kode. Melowercase dulu akan membuat `Ab3!Xy9#`
     * dan `ab3!xy9#` menjadi kode yang sama — separuh alfabetnya hilang dan
     * brute force jadi jauh lebih murah.
     */
    public static function hash(string $plain): string
    {
        return hash('sha256', trim($plain));
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
