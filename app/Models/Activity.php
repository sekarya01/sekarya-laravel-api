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
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ActivityStatus::class,
            'agreed_amount' => 'integer',
            'proof_photos' => 'array',
            'opened_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
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
