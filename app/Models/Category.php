<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'description', 'icon',
        'ref_price_min', 'ref_price_max', 'ref_price_median',
        'ref_sample_size', 'ref_computed_at', 'is_active', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'ref_price_min' => 'integer',
            'ref_price_max' => 'integer',
            'ref_price_median' => 'integer',
            'ref_sample_size' => 'integer',
            'ref_computed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return HasMany<Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Harga referensi berasal dari data nyata, atau masih nilai seed manual?
     * UI wajib membedakan keduanya — jangan tampilkan seed seolah data nyata.
     */
    public function hasRealPriceData(): bool
    {
        return $this->ref_sample_size > 0;
    }

    /** @param Builder<$this> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
