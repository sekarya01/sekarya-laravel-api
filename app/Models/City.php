<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu kabupaten/kota (B12). Data acuan — hanya dibaca lewat `GET cities`.
 */
final class City extends Model
{
    /** Data acuan, tidak disunting lewat API. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['name', 'province', 'type', 'sort_order'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
