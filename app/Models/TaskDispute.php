<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisputeCategory;
use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use App\Models\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sengketa atas hasil SATU MITRA (activity) — diajukan pemberi kerja,
 * ditanggapi mitra (sekali), diputuskan pengelola. Satu tiket terbuka per
 * activity, dijaga indeks unique `open_activity_lock`.
 */
final class TaskDispute extends Model
{
    use HasUlid;

    /**
     * KOSONG, sengaja: status & keputusan menentukan ke mana uang bergerak,
     * jadi tidak ada kolom yang boleh disetel dari larik atribut. Ditulis
     * lewat forceFill di Action (Raise/Respond/ResolveDispute).
     */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'category' => DisputeCategory::class,
            'evidence_photos' => 'array',
            'worker_evidence_photos' => 'array',
            'worker_responded_at' => 'datetime',
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

    /**
     * Mitra yang disengketakan. NULL hanya untuk tiket lama yang ambigu.
     *
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'resolved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function raiser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }
}
