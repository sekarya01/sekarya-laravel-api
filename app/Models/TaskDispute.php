<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tiket kendala atas task `disputed` (G5) — diajukan peserta, diputuskan admin.
 */
final class TaskDispute extends Model
{
    use HasUlid;

    protected $fillable = [
        'task_id', 'raised_by', 'reason', 'evidence_photos',
        'status', 'resolution', 'resolved_by', 'resolved_at', 'admin_note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'evidence_photos' => 'array',
            'status' => DisputeStatus::class,
            'resolution' => DisputeResolution::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }
}
