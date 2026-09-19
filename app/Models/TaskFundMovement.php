<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pergerakan dana tugas — rujukan mutasi dompet pemberi kerja.
 * Lihat App\Support\TaskEscrow.
 */
final class TaskFundMovement extends Model
{
    public const KIND_HOLD = 'hold';

    public const KIND_RELEASE = 'release';

    public const KIND_REFUND = 'refund';

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
