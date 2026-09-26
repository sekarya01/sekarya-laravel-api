<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu tugas yang disimpan seseorang (B11). Append-only: hanya dibuat dan
 * dihapus, tidak pernah disunting — karena itu tanpa `updated_at`.
 */
final class TaskBookmark extends Model
{
    /** Tidak ada `updated_at`. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['user_id', 'task_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'task_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
