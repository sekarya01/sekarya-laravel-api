<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CancelRequestStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TaskCancelRequest extends Model
{
    /** @use HasFactory<TaskCancelRequestFactory> */
    use HasFactory, HasUlid;

    protected $fillable = [
        'task_id', 'requested_by', 'reason', 'status',
        'decided_by', 'decided_at', 'withdrawn_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // Kunci pemilik DI-CAST (lihat komentar yang sama di Bid):
            // Policy membandingkannya dengan `$user->getKey()` memakai `===`.
            'task_id' => 'integer',
            'requested_by' => 'integer',
            'decided_by' => 'integer',
            'status' => CancelRequestStatus::class,
            'decided_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === CancelRequestStatus::Pending;
    }
}
