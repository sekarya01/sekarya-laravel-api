<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tagihan satu task (satu per task, banyak activity). Task baru lahir `held`
 * lewat `App\Support\TaskEscrow`; jalur transfer manual hanya untuk task lama.
 * Status Held adalah gerbang MULAI BEKERJA dan pelepasan upah — bukan pembuka
 * Activity (activity lahir saat DEAL, `WorkOpening`).
 */
final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    protected $fillable = ['task_id', 'payer_id', 'status', 'amount'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'reported_at' => 'datetime',
            'paid_at' => 'datetime',
            'held_at' => 'datetime',
            'released_at' => 'datetime',
            'refunded_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /** @return HasOne<Activity, $this> */
    public function activity(): HasOne
    {
        return $this->hasOne(Activity::class);
    }
}
