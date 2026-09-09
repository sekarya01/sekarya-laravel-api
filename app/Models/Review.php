<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewerRole;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    protected $fillable = [
        'task_id', 'reviewer_id', 'reviewee_id', 'reviewer_role',
        'rating', 'comment', 'is_visible',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reviewer_role' => ReviewerRole::class,
            'rating' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }

    /** @param Builder<$this> $query */
    public function scopeVisible(Builder $query): void
    {
        $query->where('is_visible', true);
    }

    /** @param Builder<$this> $query */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
