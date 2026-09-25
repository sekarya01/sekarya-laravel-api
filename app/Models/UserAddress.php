<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alamat tersimpan milik seseorang — satu per orang (`user_id` unique).
 *
 * Batas pengungkapan: hanya keluar lewat `GET me/address` milik pemiliknya.
 * Tidak ada Resource lain yang memuat relasi ini, termasuk `UserResource`
 * dan `PublicUserResource`.
 */
final class UserAddress extends Model
{
    /** @var list<string> */
    protected $fillable = ['label', 'address_line', 'city', 'province', 'latitude', 'longitude'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
