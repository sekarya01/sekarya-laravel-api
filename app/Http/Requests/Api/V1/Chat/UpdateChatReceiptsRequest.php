<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateChatReceiptsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Pesan TERAKHIR yang sudah sampai / sudah dibaca di perangkat.
            // Semua pesan sebelum id itu ikut tertandai.
            'delivered_message_id' => ['required_without:read_message_id', 'nullable', 'string', 'size:26'],
            'read_message_id' => ['required_without:delivered_message_id', 'nullable', 'string', 'size:26'],
        ];
    }
}
