<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Skill;
use Illuminate\Http\Request;

/** @mixin Skill */
final class SkillResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'category' => CategoryResource::make($this->whenLoaded('category')),
        ];
    }
}
