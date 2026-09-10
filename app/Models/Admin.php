<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminAction;
use App\Enums\AdminRole;
use App\Enums\AdminStatus;
use App\Enums\TokenAbility;
use App\Exceptions\Domain\SuperAdminIsProtectedException;
use App\Models\Concerns\HasUlid;
use Database\Factories\AdminFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Pengelola aplikasi. Model kedua yang memegang token Sanctum.
 *
 * Bukan `users` dengan kolom peran — alasan lengkapnya di migrasi
 * `create_admins_table`. Ringkasnya: `users` punya jalur tulis publik
 * (pendaftaran, sunting profil), tabel ini tidak punya satu pun.
 *
 * @property-read string|null $super_admin_lock kolom TURUNAN, jangan ditulis
 */
final class Admin extends Authenticatable
{
    /** @use HasFactory<AdminFactory> */
    use HasApiTokens, HasFactory, HasUlid, Notifiable, SoftDeletes;

    /**
     * `role` dan `status` TIDAK ada di sini — keduanya pembawa hak akses.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password'];

    /** @var list<string> */
    protected $hidden = ['password'];

    /**
     * Tidak ada sesi berbasis cookie untuk pengelola, jadi tidak ada kolom
     * `remember_token`. Dikosongkan supaya tidak ada kode framework yang
     * mencoba menulis kolom yang tidak ada.
     */
    protected $rememberTokenName = '';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => AdminRole::class,
            'status' => AdminStatus::class,
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * super_admin tidak bisa dihapus, dan penjaganya di SINI.
     *
     * Bukan hanya di Action yang melayani endpoint DELETE: penghapusan bisa
     * datang dari command, dari tinker, atau dari Action lain yang belum ada.
     * Pola yang sama dengan hook `saved` di Task — invarian yang kalau
     * terlewat tidak menimbulkan galat apa pun ditegakkan di satu titik yang
     * dilewati semua jalur.
     *
     * Yang tidak bisa dijaga dari sini: `DELETE FROM admins` langsung di
     * phpMyAdmin. Itu sebabnya kolom `super_admin_lock` ada — ia menjaga
     * jumlahnya, bukan keberadaannya.
     */
    protected static function booted(): void
    {
        self::deleting(function (self $admin): void {
            if ($admin->role->isSuperAdmin()) {
                throw SuperAdminIsProtectedException::cannotBeDeleted();
            }
        });
    }

    public function accessAbility(): TokenAbility
    {
        return TokenAbility::AdminAccess;
    }

    public function refreshAbility(): TokenAbility
    {
        return TokenAbility::AdminRefresh;
    }

    /**
     * Boleh melakukan tindakan ini?
     *
     * Namanya bukan `can()` — nama itu sudah dipakai trait Authorizable milik
     * Laravel dengan tanda tangan yang berbeda, dan menimpanya akan mematahkan
     * seluruh pemeriksaan Gate/Policy pada model ini.
     */
    public function mayPerform(AdminAction $action): bool
    {
        return $this->role->can($action);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role->isSuperAdmin();
    }

    /**
     * Keyset ordering. `id` wajib sebagai tiebreaker — tanpa itu cursor
     * bisa skip/ulang saat beberapa baris punya created_at sama.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    /** @return HasMany<AdminAuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class);
    }
}
