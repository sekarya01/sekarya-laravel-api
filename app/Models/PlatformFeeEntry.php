<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pendapatan platform dari biaya layanan (G6): satu baris per activity yang
 * upahnya dibayarkan. Append-only, sama seperti buku besar dompet.
 */
final class PlatformFeeEntry extends Model
{
    use HasUlid;

    protected $fillable = [
        'activity_id', 'worker_id', 'gross_amount', 'fee_amount', 'percent_bp',
    ];

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<User, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'worker_id');
    }
}
