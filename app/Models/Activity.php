<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActivityStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\ActivityFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Eksekusi pekerjaan. Keberadaan barisnya sendiri menegakkan aturan:
 * activity tidak bisa ada tanpa pembayaran yang sudah ditahan.
 */
final class Activity extends Model
{
    /** @use HasFactory<ActivityFactory> */
    use HasFactory, HasUlid;

    protected $fillable = [
        'task_id', 'worker_id', 'payment_id', 'status',
        'agreed_amount', 'opened_at', 'worker_note', 'proof_photos', 'poster_note',
        'live_latitude', 'live_longitude', 'live_updated_at', 'checklist_state',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // Kunci pemilik DI-CAST, dan itu bukan kosmetik.
            //
            // Policy membandingkannya dengan `$user->getKey()` memakai `===`.
            // Laravel meng-cast primary key model sendiri ke int (getCasts()
            // menggabungkan keyName => keyType), tapi kolom asing seperti ini
            // tidak dicast apa pun — nilainya apa adanya dari driver. Begitu
            // driver mengembalikannya sebagai string, `int === string` bernilai
            // false dan PEMILIK ASLI ditolak 403, sementara kueri yang
            // membandingkannya di SQL tetap lolos karena MySQL menyamakan tipe.
            // Persis itu yang terjadi di produksi: `GET /tasks/posted` berisi
            // task orangnya, tapi `PUT /tasks/{task}` menjawab 403.
            'task_id' => 'integer',
            'worker_id' => 'integer',
            'status' => ActivityStatus::class,
            'agreed_amount' => 'integer',
            'proof_photos' => 'array',
            'opened_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            // Lokasi langsung (B8) — hanya saat `on_the_way`.
            'live_latitude' => 'decimal:7',
            'live_longitude' => 'decimal:7',
            'live_updated_at' => 'datetime',
            // Checklist (B10): larik boolean sejajar `tasks.checklist`.
            'checklist_state' => 'array',
        ];
    }

    /**
     * Update kemajuan terbaru (B9). Satu baris terakhir, bukan seluruh riwayat —
     * yang ditampilkan kartu adalah kalimat terakhir ("09.52 · tiba di lokasi").
     *
     * @return HasOne<ActivityUpdate, $this>
     */
    public function latestUpdate(): HasOne
    {
        return $this->hasOne(ActivityUpdate::class)->latestOfMany();
    }

    /** @return HasMany<ActivityUpdate, $this> */
    public function updates(): HasMany
    {
        return $this->hasMany(ActivityUpdate::class);
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @param Builder<$this> $query */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
