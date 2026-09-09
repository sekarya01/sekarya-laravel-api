<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Data\CursorPageData;
use App\Enums\ReviewerRole;
use App\Models\Review;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;

final class ListUserReviewsAction
{
    /** @return CursorPaginator<int, Review> */
    public function forUser(
        User $user,
        CursorPageData $page,
        ?ReviewerRole $role = null,
    ): CursorPaginator {
        return Review::query()
            ->where('reviewee_id', $user->getKey())
            ->visible()
            ->when($role, fn (Builder $q, ReviewerRole $r) => $q->where('reviewer_role', $r))
            ->with('reviewer')
            ->latestFirst()
            ->cursorPaginate($page->perPage);
    }
}
