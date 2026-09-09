<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Models\Concerns\HasUlid;
use App\Support\SearchTerms;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    protected $fillable = [
        'poster_id', 'category_id', 'title', 'description', 'options', 'photos',
        'budget_min', 'budget_max', 'ref_price_median',
        'location_text', 'city', 'latitude', 'longitude', 'is_remote',
        'needed_at', 'bidding_closes_at', 'status', 'workers_needed',
    ];

    protected static function booted(): void
    {
        self::creating(function (self $task): void {
            $task->task_number ??= self::generateNumber();
        });

        // Baris indeks pencarian dijaga DI SINI, bukan di Action.
        //
        // Ada tiga jalur yang menulis judul sebuah task — pembuatan, penyuntingan,
        // dan factory di test — dan indeks yang meleset tidak menimbulkan galat
        // apa pun: task itu sekadar tidak pernah muncul di hasil pencarian.
        // Menaruhnya di sini berarti tidak ada jalur yang bisa lupa.
        self::saved(function (self $task): void {
            if ($task->wasRecentlyCreated || $task->wasChanged(['title', 'description'])) {
                $task->syncSearchIndex();
            }
        });
    }

    /**
     * Tulis ulang baris `task_search` milik task ini.
     *
     * Teks yang disimpan sudah dinormalisasi (lihat SearchTerms) dan sengaja
     * tidak sama dengan judulnya — ia memuat akar kata dan bentuk bersentinel
     * untuk kata pendek, yang tidak layak dibaca manusia.
     */
    public function syncSearchIndex(): void
    {
        DB::table('task_search')->updateOrInsert(
            ['task_id' => $this->getKey()],
            ['terms' => (new SearchTerms)->forIndex(
                (string) $this->title,
                (string) $this->description,
            )],
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'photos' => 'array',
            'budget_min' => 'integer',
            'budget_max' => 'integer',
            'ref_price_median' => 'integer',
            'agreed_amount' => 'integer',
            'bids_count' => 'integer',
            'workers_needed' => 'integer',
            'workers_hired' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_remote' => 'boolean',
            'status' => TaskStatus::class,
            'needed_at' => 'datetime',
            'bidding_closes_at' => 'datetime',
            'dealt_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    private static function generateNumber(): string
    {
        return sprintf('TK-%s-%s', now()->format('ymd'), strtoupper(Str::random(6)));
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'poster_id');
    }

    /**
     * Pekerja yang diterima pada task ini.
     *
     * Lewat `bids`, bukan kolom di `tasks`: sebuah task bisa merekrut banyak
     * orang, dan satu kolom tidak bisa menyimpan tiga puluh nilai. Menyimpan
     * salinannya "untuk yang satu orang" berarti dua sumber kebenaran untuk
     * pertanyaan yang sama, dan yang satu pasti melenceng.
     *
     * @return BelongsToMany<User, $this>
     */
    public function workers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bids', 'task_id', 'bidder_id')
            ->wherePivot('status', BidStatus::Accepted->value);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Keahlian yang dibutuhkan task ini. Pivot berindeks, bukan kolom JSON —
     * lihat migrasi create_skills_tables untuk alasannya.
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /** @return HasMany<Bid, $this> */
    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    /**
     * Tawaran milik satu orang pada task ini. Selalu dipakai dengan batasan
     * bidder_id di eager-load — ada supaya feed bisa menandai "sudah dilamar"
     * tanpa panggilan API kedua.
     *
     * @return HasOne<Bid, $this>
     */
    public function myBid(): HasOne
    {
        return $this->hasOne(Bid::class);
    }

    /**
     * Penawaran yang diterima — SUMBER KEBENARAN siapa yang mengerjakan task
     * ini, berapa orang, dan masing-masing dengan harga berapa.
     *
     * @return HasMany<Bid, $this>
     */
    public function acceptedBids(): HasMany
    {
        return $this->hasMany(Bid::class)->where('status', BidStatus::Accepted);
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Satu activity per pekerja yang diterima. Task 30 orang membuka 30
     * activity begitu dana ditahan.
     *
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /** @return HasMany<Review, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** @return HasMany<TaskStatusLog, $this> */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(TaskStatusLog::class);
    }

    /**
     * budget_max nullable, jadi perbandingan HARUS menoleransi null.
     * `BETWEEN min AND max` akan menghasilkan nol baris saat max null, bukan "semua".
     */
    public function isWithinBudget(int $amount): bool
    {
        return $amount >= $this->budget_min
            && ($this->budget_max === null || $amount <= $this->budget_max);
    }

    /**
     * Slot yang belum terisi. Nol berarti perekrutan selesai.
     */
    public function slotsRemaining(): int
    {
        return max(0, $this->workers_needed - $this->workers_hired);
    }

    public function isFullyStaffed(): bool
    {
        return $this->workers_hired >= $this->workers_needed;
    }

    /**
     * Semua pekerja sudah menyerahkan hasilnya.
     *
     * Status TASK mengikuti agregat, bukan pekerja yang kebetulan paling cepat.
     * Tanpa ini, pada task 30 orang penyerahan pertama menandai seluruh task
     * `submitted` — dan 29 penyerahan berikutnya ditolak karena task-nya sudah
     * pindah status.
     */
    public function everyWorkerHasSubmitted(): bool
    {
        return ! $this->activities()
            ->whereIn('status', [
                ActivityStatus::Open,
                ActivityStatus::InProgress,
                ActivityStatus::Rejected,
            ])
            ->exists();
    }

    /**
     * Semua pekerja sudah disetujui.
     *
     * Ini syarat pelepasan dana. Melepas pada persetujuan PERTAMA berarti
     * seluruh dana keluar untuk satu orang, dan 29 sisanya mengerjakan
     * pekerjaan yang tidak akan pernah dibayar.
     */
    public function everyWorkerIsApproved(): bool
    {
        return $this->activities()->exists()
            && ! $this->activities()->where('status', '!=', ActivityStatus::Approved)->exists();
    }

    /**
     * SATU definisi "bisa dilamar", dipakai bersama oleh feed pencari kerja
     * dan pemeriksaan di PlaceBidAction.
     *
     * Sebelumnya feed hanya memeriksa status, sehingga task yang masa lelangnya
     * sudah lewat tetap tampil sebagai siap dilamar lalu menolak lamaran.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBiddable(Builder $query): void
    {
        $query->where('status', TaskStatus::Open)
            ->where(fn (Builder $q) => $q
                ->whereNull('bidding_closes_at')
                ->orWhere('bidding_closes_at', '>', now()));
    }

    /** Masa lelang sudah lewat, meski status masih open. */
    public function isBiddingClosed(): bool
    {
        return $this->bidding_closes_at !== null && $this->bidding_closes_at->isPast();
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
}
