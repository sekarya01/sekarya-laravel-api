<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Bid;

use Illuminate\Foundation\Http\FormRequest;

final class PlaceBidRequest extends FormRequest
{
    /**
     * Catatan: nominal TIDAK divalidasi terhadap budget_max di sini.
     * budget_max hanya petunjuk — poster diberi kebebasan penuh memilih,
     * termasuk tawaran di atas anggarannya. budget_min dicek di Action
     * karena itu validasi state, bukan bentuk.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'message' => ['nullable', 'string', 'max:1000'],
            'option_responses' => ['sometimes', 'array', 'max:20'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0.25', 'max:999'],
            'can_start_at' => ['nullable', 'date', 'after_or_equal:now'],
        ];
    }
}
