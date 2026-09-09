<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * ID publik untuk model yang id-nya muncul di URL.
 *
 * `id` berurutan membocorkan volume bisnis (orang bisa menghitung berapa task
 * masuk per hari) dan gampang di-enumerate — jadi rute memakai ULID.
 *
 * Satu tempat untuk: pembuatan ULID, dan route key. Sebelumnya keduanya
 * diulang di setiap model.
 */
trait HasUlid
{
    public static function bootHasUlid(): void
    {
        static::creating(function (Model $model): void {
            $model->ulid ??= (string) Str::ulid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
