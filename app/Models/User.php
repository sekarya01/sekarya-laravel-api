<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BidStatus;
use App\Enums\Gender;
use App\Enums\TokenAbility;
use App\Enums\UserActiveMode;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Concerns\HasUlid;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'name', 'gender', 'birth_date', 'email', 'phone', 'password', 'avatar_path', 'bio',
        'active_mode', 'address_line', 'city', 'province', 'postal_code', 'theme',
    ];

    /** @var list<string> */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Profil pekerja ikut termuat SETIAP KALI seorang pengguna dimuat.
     *
     * Blunt, dan disengaja. Sejak reputasi pindah ke `user_workers`, hampir
     * setiap tempat yang menampilkan seorang pengguna membutuhkannya:
     * daftar penawaran, kartu pekerja di task, penilaian, antrean moderasi.
     * Menyebutkannya satu per satu di setiap Action berarti satu yang
     * terlewat = N+1 yang tidak menimbulkan galat apa pun — halamannya tetap
     * benar, hanya melambat sebanding jumlah barisnya, dan itu baru terasa di
     * produksi.
     *
     * Harganya satu kueri berindeks (`user_workers.user_id` unique) per
     * pemuatan pengguna.
     *
     * @var list<string>
     */
    protected $with = ['workerProfile'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'gender' => Gender::class,
            'active_mode' => UserActiveMode::class,
            'status' => UserStatus::class,
            'poster_rating_avg' => 'decimal:2',
        ];
    }

    /**
     * Umur, DIHITUNG dari tanggal lahir. Tidak pernah disimpan.
     *
     * Kolom `age` akan salah pada hari ulang tahun setiap penggunanya, dan
     * tidak ada satu pun kejadian di aplikasi ini yang bisa memicu
     * pembaruannya — tidak ada permintaan HTTP yang datang karena seseorang
     * bertambah tua. Yang bisa menjaganya cuma cron harian yang memindai
     * seluruh tabel, untuk angka yang biayanya satu pengurangan.
     *
     * Alasan yang sama membuat kolom turunan MySQL juga tidak bisa dipakai:
     * `CURDATE()` non-deterministik, dan GENERATED menolaknya.
     */
    protected function age(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->birth_date === null
            ? null
            : (int) $this->birth_date->diffInYears(now()));
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

    /**
     * Sisi pekerja dari akun ini — nol atau satu baris.
     *
     * @return HasOne<UserWorker, $this>
     */
    public function workerProfile(): HasOne
    {
        return $this->hasOne(UserWorker::class);
    }

    /**
     * Profil pekerja yang pasti ADA DI MEMORI, tanpa menulis apa pun.
     *
     * Dipakai jalur BACA. Sebuah GET yang membuat baris berarti sekadar
     * membuka profil sendiri sudah menambah baris di basis data, dan permintaan
     * yang seharusnya aman jadi punya efek samping.
     *
     * Instance yang dikembalikan bisa saja belum tersimpan; relasi `user`
     * selalu dipasang supaya resolusi "NULL = pakai punya akun" tetap bekerja
     * tanpa satu kueri tambahan pun.
     */
    public function workerProfileOrNew(): UserWorker
    {
        $profile = $this->relationLoaded('workerProfile')
            ? $this->getRelation('workerProfile')
            : $this->workerProfile()->first();

        $profile ??= new UserWorker;
        $profile->setRelation('user', $this);

        return $profile;
    }

    /**
     * Profil pekerja yang pasti TERSIMPAN. Dipakai jalur TULIS — Action yang
     * menaikkan agregat reputasi tidak bisa menunggu profilnya dibuat manual:
     * orang bisa memenangkan penawaran tanpa pernah membuka halaman profil
     * pekerjanya, dan `increment()` pada baris yang tidak ada hilang diam-diam.
     */
    public function workerProfileOrCreate(): UserWorker
    {
        $profile = $this->workerProfile()->firstOrCreate();

        $this->setRelation('workerProfile', $profile);

        return $profile;
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
     * Siap menerima pekerjaan: profil pekerjanya sudah terdaftar.
     *
     * DIHITUNG dari ada-tidaknya baris `user_workers`, bukan kolom boolean di
     * `users` — persis alasan yang sama dengan badge terverifikasi di bawah.
     * Sebuah kolom akan menjawab pertanyaan ini dari tempat yang bukan
     * sumbernya, dan ia bisa melenceng lewat jalur mana pun yang membuat atau
     * menghapus profil: Action perekrutan, penghapusan akun beruntun, satu
     * DELETE di phpMyAdmin. Yang tertinggal bukan sekadar angka salah — ia
     * pekerja yang muncul di daftar padahal profilnya sudah tidak ada, atau
     * sebaliknya.
     *
     * Gratis di jalur normal: `$with` sudah memuat relasinya.
     */
    public function readyToWork(): bool
    {
        return $this->hasWorkerProfile() && $this->isIdentityVerified();
    }

    /** Baris `user_workers`-nya ada. Sendirian ini BUKAN "siap kerja". */
    public function hasWorkerProfile(): bool
    {
        return $this->relationLoaded('workerProfile')
            ? $this->getRelation('workerProfile') !== null
            : $this->workerProfile()->exists();
    }

    /**
     * Punya jenis kelamin DAN tanggal lahir.
     *
     * Syarat masuk profil pekerja: keduanya muncul di kartu pekerja yang
     * dibaca pemberi kerja, dan profil yang menampilkan dua tanda hubung
     * bukan profil yang bisa dipakai memilih orang.
     */
    public function hasCompleteIdentity(): bool
    {
        return $this->gender !== null && $this->birth_date !== null;
    }

    /**
     * Badge "terverifikasi" dihitung, tidak disimpan sebagai boolean —
     * status verifikasi bisa dicabut dan boolean yang tertinggal akan bohong.
     */
    public function isIdentityVerified(): bool
    {
        // `identity_verified_count` datang dari withCount() kalau di-eager
        // load. Sejak `readyToWork()` ikut memanggil ini, jalur kueri-nya
        // dipanggil dua kali per baris di setiap daftar — dan daftar pekerja
        // mengembalikan sampai 50 orang sekaligus.
        if (isset($this->identity_verified_count)) {
            return $this->identity_verified_count > 0;
        }

        return $this->verifications()
            ->where('type', VerificationType::Identity)
            ->where('status', VerificationStatus::Verified)
            ->exists();
    }
}
