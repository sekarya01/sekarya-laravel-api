<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerInviteRedemption extends Model
{
    protected $fillable = ['invite_code_id', 'user_id'];

    /** @return BelongsTo<WorkerInviteCode, $this> */
    public function inviteCode(): BelongsTo
    {
        return $this->belongsTo(WorkerInviteCode::class, 'invite_code_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
