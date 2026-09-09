<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Review;
use Illuminate\Http\Request;

/** @mixin Review */
final class ReviewResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'reviewer_role' => $this->reviewer_role->value,
            'reviewer' => PublicUserResource::make($this->whenLoaded('reviewer')),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
