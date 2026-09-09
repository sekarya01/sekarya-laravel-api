<?php

declare(strict_types=1);

namespace App\Actions\Category;

use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;

final class ListCategoriesAction
{
    /** @return Collection<int, Category> */
    public function handle(): Collection
    {
        return Category::query()->active()->get();
    }
}
