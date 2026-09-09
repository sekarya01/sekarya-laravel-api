<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\VerificationType;
use App\Models\UserVerification;
use Illuminate\Http\Request;

/**
 * Batas pengungkapan paling ketat di seluruh API.
 *
 * TIDAK pernah keluar dari sini: path foto KTP, path foto selfie, NIK
 * (hash maupun terenkripsi), dan nomor rekening. Yang keluar hanya STATUS.
 *
 * Foto hanya boleh diakses lewat signed URL terpisah yang memverifikasi
 * pemiliknya — bukan dengan menaruh path di response ini.
 *
 * @mixin UserVerification
 */
final class VerificationResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'is_verified' => $this->status->isVerified(),
            // Cukup "sudah diunggah atau belum" — path-nya tidak keluar.
            'has_id_card_photo' => $this->id_card_photo_path !== null,
            'has_selfie_photo' => $this->selfie_photo_path !== null,
            'name_on_document' => $this->when(
                $this->type === VerificationType::Identity,
                fn (): ?string => $this->name_on_document,
            ),
            'bank_code' => $this->when(
                $this->type === VerificationType::BankAccount,
                fn (): ?string => $this->bank_code,
            ),
            'account_holder_name' => $this->when(
                $this->type === VerificationType::BankAccount,
                fn (): ?string => $this->account_holder_name,
            ),
            'rejection_reason' => $this->rejection_reason,
            'submitted_at' => $this->iso($this->submitted_at),
            'reviewed_at' => $this->iso($this->reviewed_at),
        ];
    }
}
