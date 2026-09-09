<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BidStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\BidFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Bid extends Model
{
    /** @use HasFactory<BidFactory> */
    use HasFactory, HasUlid;

    protected $fillable = [
        'task_id', 'bidder_id', 'amount', 'message',
        'option_responses', 'estimated_hours', 'can_start_at', 'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'option_responses' => 'array',
            'estimated_hours' => 'decimal:2',
            'can_start_at' => 'datetime',
            'responded_at' => 'datetime',
            'status' => BidStatus::class,
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return BelongsTo<User, $this> */
    public function bidder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bidder_id');
    }

    /** @param Builder<$this> $query */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
