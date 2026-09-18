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
            // Kunci pemilik DI-CAST, dan itu bukan kosmetik.
            //
            // Policy membandingkannya dengan `$user->getKey()` memakai `===`.
            // Laravel meng-cast primary key model sendiri ke int (getCasts()
            // menggabungkan keyName => keyType), tapi kolom asing seperti ini
            // tidak dicast apa pun — nilainya apa adanya dari driver. Begitu
            // driver mengembalikannya sebagai string, `int === string` bernilai
            // false dan PEMILIK ASLI ditolak 403, sementara kueri yang
            // membandingkannya di SQL tetap lolos karena MySQL menyamakan tipe.
            // Persis itu yang terjadi di produksi: `GET /tasks/posted` berisi
            // task orangnya, tapi `PUT /tasks/{task}` menjawab 403.
            'task_id' => 'integer',
            'bidder_id' => 'integer',
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
