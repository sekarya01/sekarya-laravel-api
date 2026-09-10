<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BidStatus;
use App\Enums\TokenAbility;
use App\Enums\UserActiveMode;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Concerns\HasUlid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlid, Notifiable, SoftDeletes;

    /**
     * Kolom hak akses (status, role) sengaja tidak ada di sini.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'phone', 'password', 'avatar_path', 'bio',
        'active_mode', 'address_line', 'city', 'province', 'postal_code', 'theme',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'password' => 'hashed',
            'active_mode' => UserActiveMode::class,
            'status' => UserStatus::class,
            'worker_rating_avg' => 'decimal:2',
            'poster_rating_avg' => 'decimal:2',
        ];
    }

    /**
     * Ability yang dibawa token milik pengguna.
     *
     * Ada di model, bukan di TokenIssuer: sejak `admins` juga memegang token,
     * penerbitnya harus bisa menerbitkan untuk dua jenis pemilik tanpa
     * mencabang pada kelasnya. Yang menentukan "token ini boleh apa" adalah
     * pemiliknya sendiri.
     */
    public function accessAbility(): TokenAbility
    {
        return TokenAbility::Access;
    }

    public function refreshAbility(): TokenAbility
    {
        return TokenAbility::Refresh;
    }

    /**
     * Keahlian yang dimiliki. Dipakai untuk filter feed "cocok dengan skill saya".
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /** @return HasMany<UserVerification, $this> */
    public function verifications(): HasMany
    {
        return $this->hasMany(UserVerification::class);
    }

    /** Task yang dia posting (sebagai pemberi kerja). @return HasMany<Task, $this> */
    public function postedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'poster_id');
    }

    /**
     * Task yang dia kerjakan (sebagai penerima kerja).
     *
     * Lewat `bids`, bukan kolom di `tasks`: satu task bisa merekrut banyak
     * orang, jadi hubungannya many-to-many dan sumber kebenarannya adalah
     * penawaran yang diterima.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function workedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'bids', 'bidder_id', 'task_id')
            ->wherePivot('status', BidStatus::Accepted->value);
    }

    /** @return HasMany<Bid, $this> */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'bidder_id');
    }

    /** @return HasMany<Activity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'worker_id');
    }

    /** @return HasMany<Review, $this> */
    public function receivedReviews(): HasMany
    {
        return $this->hasMany(Review::class, 'reviewee_id');
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

    /**
     * Badge "terverifikasi" dihitung, tidak disimpan sebagai boolean —
     * status verifikasi bisa dicabut dan boolean yang tertinggal akan bohong.
     */
    public function isIdentityVerified(): bool
    {
        return $this->verifications()
            ->where('type', VerificationType::Identity)
            ->where('status', VerificationStatus::Verified)
            ->exists();
    }
}
