<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UserQueueRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(UserStatus::class)],

            // Pencocokan PERSIS, bukan pencarian sebagian — lihat UserQueueData.
            // Tanpa `exists`: daftar pengguna bukan tempat memberi tahu
            // penanya alamat mana yang terdaftar lewat kode 422.
            'email' => ['sometimes', 'email:rfc', 'max:180'],
        ];
    }
}
