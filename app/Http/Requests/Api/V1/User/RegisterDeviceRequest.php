<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Enums\DevicePlatform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class RegisterDeviceRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Batas 255 sama dengan lebar kolom `device_tokens.token`. Token
            // yang lebih panjang ditolak sebagai 422 yang jelas, bukan gagal
            // saat menulis baris.
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', Rule::enum(DevicePlatform::class)],
        ];
    }
}
