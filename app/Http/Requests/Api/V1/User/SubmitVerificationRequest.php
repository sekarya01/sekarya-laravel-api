<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Enums\VerificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SubmitVerificationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $isIdentity = $this->string('type')->value() === VerificationType::Identity->value;

        return [
            'type' => ['required', Rule::enum(VerificationType::class)],

            // Foto KTP + selfie dikirim bersamaan — keduanya dinilai bersama,
            // gunanya selfie justru untuk dicocokkan dengan foto di KTP.
            'id_card_photo_path' => [Rule::requiredIf($isIdentity), 'string', 'max:255'],
            'selfie_photo_path' => [Rule::requiredIf($isIdentity), 'string', 'max:255'],
            'document_number' => [Rule::requiredIf($isIdentity), 'digits:16'],
            'name_on_document' => [Rule::requiredIf($isIdentity), 'string', 'max:120'],
            'birth_date_on_document' => ['nullable', 'date', 'before:today'],

            'bank_code' => [Rule::requiredIf(! $isIdentity), 'string', 'max:20'],
            'account_number' => [Rule::requiredIf(! $isIdentity), 'string', 'max:40'],
            'account_holder_name' => [Rule::requiredIf(! $isIdentity), 'string', 'max:120'],
        ];
    }
}
