<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu catatan kemajuan pekerja pada sebuah activity (B9).
 *
 * Append-only: hanya dibuat dan dibaca, karena itu tanpa `updated_at` dan
 * tanpa `$fillable` — barisnya disusun `CreateActivityUpdateAction`.
 */
final class ActivityUpdate extends Model
{
    /** Tidak ada `updated_at`. */
    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $fillable = ['activity_id', 'note', 'photo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'activity_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Activity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
