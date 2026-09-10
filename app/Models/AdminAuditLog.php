<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdminAction;
use Database\Factories\AdminAuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only. Jejak yang bisa disunting bukan jejak.
 *
 * Tidak ada relasi `subject()` polimorfis: `subject_type` menyimpan slug
 * tabel, bukan nama kelas PHP, justru supaya jejak lama tetap terbaca setelah
 * kelasnya dipindah atau diganti nama. Yang ingin menelusuri subjeknya
 * melakukannya lewat slug + id secara sadar.
 *
 * @property-read AdminAction $action
 */
final class AdminAuditLog extends Model
{
    /** @use HasFactory<AdminAuditLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_id', 'action', 'subject_type', 'subject_id', 'reason', 'ip',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => AdminAction::class,
            'subject_id' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Jejak satu TINDAKAN atas satu baris.
     *
     * Menyaring ketiganya — tindakan, jenis subjek, dan id-nya. Menyaring
     * subjek saja tampak cukup dan tidak: satu baris pembayaran menerima
     * jejak `payment.confirmed` DAN `payment.rejected`, jadi kueri yang hanya
     * menyebut subjeknya mengembalikan keduanya. Jenis subjek tetap
     * diturunkan dari tindakannya, supaya tidak ada pemanggil yang bisa
     * memasangkan tindakan dengan slug yang bukan miliknya.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeForAction(Builder $query, AdminAction $action, int $subjectId): void
    {
        $query->where('action', $action)
            ->where('subject_type', $action->subjectType())
            ->where('subject_id', $subjectId);
    }
}
