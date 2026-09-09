<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only. Jejak yang bisa diedit bukan jejak. */
final class TaskStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'task_id', 'from_status', 'to_status', 'actor_type', 'actor_id', 'reason', 'metadata',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
