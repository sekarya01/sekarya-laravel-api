<?php

declare(strict_types=1);

namespace App\Actions\Skill;

use App\Models\Skill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ListSkillsAction
{
    /** @return Collection<int, Skill> */
    public function handle(?int $categoryId = null): Collection
    {
        return Skill::query()
            ->active()
            ->when($categoryId, fn (Builder $q, int $id) => $q->where('category_id', $id))
            ->with('category')
            ->get();
    }
}
