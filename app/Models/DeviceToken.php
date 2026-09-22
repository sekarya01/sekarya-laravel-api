<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DevicePlatform;
use Database\Factories\DeviceTokenFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pemasangan aplikasi yang boleh menerima push.
 *
 * Tanpa ULID: baris ini tidak pernah muncul di URL. Ia hanya dikenali server
 * lewat `token`-nya, yang justru unique.
 */
final class DeviceToken extends Model
{
    /** @use HasFactory<DeviceTokenFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['user_id', 'token', 'platform'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'platform' => DevicePlatform::class,
            'last_used_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
