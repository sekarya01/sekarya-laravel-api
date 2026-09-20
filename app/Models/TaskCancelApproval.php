<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CancelApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Suara SATU pekerja atas satu permintaan pembatalan. */
final class TaskCancelApproval extends Model
{
    protected $fillable = ['cancel_request_id', 'worker_id', 'status', 'responded_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // Kunci asing DI-CAST (lihat komentar yang sama di Bid): tanpa itu
            // `int === string` menolak orang yang benar begitu driver
            // mengembalikan string.
            'cancel_request_id' => 'integer',
            'worker_id' => 'integer',
            'status' => CancelApprovalStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TaskCancelRequest, $this> */
    public function cancelRequest(): BelongsTo
    {
        return $this->belongsTo(TaskCancelRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
}
