<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\WorkerInvite;

use Illuminate\Foundation\Http\FormRequest;

final class StoreWorkerInviteCodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'max_uses' => ['sometimes', 'integer', 'min:1', 'max:100000'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
            'note' => ['sometimes', 'nullable', 'string', 'max:255'],
            // Cakupan wilayah: keduanya NULL = nasional. Parsial boleh
            // (kota saja atau provinsi saja) — pencocokannya per kolom.
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'province' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
