<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;

/** Halaman cursor daftar kode undangan & pemakainya — tanpa filter. */
final class WorkerInvitePageRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [];
    }
}
