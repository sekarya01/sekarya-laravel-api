<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Category;

use App\Actions\Category\ListCategoriesAction;
use App\Http\Resources\Api\V1\CategoryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListCategoriesController
{
    public function __construct(private readonly ListCategoriesAction $action) {}

    public function __invoke(): AnonymousResourceCollection
    {
        return CategoryResource::collection($this->action->handle());
    }
}
