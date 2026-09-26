<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\ReportUserAction;
use App\Data\User\ReportUserData;
use App\Http\Requests\Api\V1\User\ReportUserRequest;
use App\Http\Resources\Api\V1\UserReportResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Laporkan pengguna lain (G7). */
final class CreateUserReportController
{
    public function __construct(private readonly ReportUserAction $action) {}

    public function __invoke(ReportUserRequest $request, string $user): JsonResponse
    {
        $reported = User::query()->where('ulid', $user)->firstOrFail();

        $report = $this->action->handle(ReportUserData::fromRequest($request), $request->user(), $reported);

        return UserReportResource::make($report->load(['reported', 'task']))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
