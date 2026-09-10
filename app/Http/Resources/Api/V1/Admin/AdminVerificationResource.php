<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Enums\VerificationType;
use App\Http\Resources\Api\V1\BaseResource;
use App\Models\UserVerification;
use Illuminate\Http\Request;

/**
 * Satu baris ANTREAN verifikasi. Status saja, tanpa isi dokumen.
 *
 * Kelas terpisah dari AdminVerificationDetailResource, bukan satu kelas
 * dengan penanda "tampilkan yang sensitif": batas pengungkapan yang
 * ditentukan sebuah boolean akan salah nilai suatu hari, dan yang bocor
 * bukan boolean-nya. Dengan dua kelas, endpoint daftar TIDAK BISA
 * mengeluarkan NIK — ia tidak punya kodenya.
 *
 * Karena itu daftar antrean 20 baris juga tidak melakukan 20 dekripsi.
 *
 * @mixin UserVerification
 */
final class AdminVerificationResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'awaits_review' => $this->status->awaitsReview(),

            // Cukup "sudah diunggah atau belum" — path-nya tidak keluar.
            'has_id_card_photo' => $this->id_card_photo_path !== null,
            'has_selfie_photo' => $this->selfie_photo_path !== null,
            'face_match_score' => $this->face_match_score === null
                ? null
                : (float) $this->face_match_score,

            'name_on_document' => $this->when(
                $this->type === VerificationType::Identity,
                fn (): ?string => $this->name_on_document,
            ),
            'bank_code' => $this->when(
                $this->type === VerificationType::BankAccount,
                fn (): ?string => $this->bank_code,
            ),

            'submitted_at' => $this->iso($this->submitted_at),
            'reviewed_at' => $this->iso($this->reviewed_at),
            'user' => AdminUserResource::make($this->whenLoaded('user')),
        ];
    }
}
