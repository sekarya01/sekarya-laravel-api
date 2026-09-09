<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Skill;

use App\Actions\Skill\ListSkillsAction;
use App\Http\Requests\Api\V1\Skill\ListSkillsRequest;
use App\Http\Resources\Api\V1\SkillResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Daftar keahlian — untuk filter feed dan untuk menandai task. */
final class ListSkillsController
{
    public function __construct(private readonly ListSkillsAction $action) {}

    public function __invoke(ListSkillsRequest $request): AnonymousResourceCollection
    {
        return SkillResource::collection(
            $this->action->handle(
                $request->filled('category_id') ? $request->integer('category_id') : null,
            ),
        );
    }
}
