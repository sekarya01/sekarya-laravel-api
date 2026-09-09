<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\SubmitVerificationData;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Database\ConnectionInterface;

/**
 * Pengajuan verifikasi identitas.
 *
 * Pola dua kolom pada NIK: hash untuk DICARI (deteksi satu NIK dipakai
 * beberapa akun), terenkripsi untuk DIBACA saat sengketa. NIK mentah tidak
 * pernah masuk kolom yang bisa di-SELECT sembarangan maupun ke log.
 */
final class SubmitVerificationAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(SubmitVerificationData $data, User $user): UserVerification
    {
        return $this->db->transaction(function () use ($data, $user): UserVerification {
            $attributes = [
                'user_id' => $user->getKey(),
                'type' => $data->type,
                'status' => VerificationStatus::Pending,
                'submitted_at' => now(),
                'reviewed_at' => null,
                'reviewed_by' => null,
                'rejection_reason' => null,
            ];

            if ($data->type === VerificationType::Identity) {
                $attributes += [
                    'id_card_photo_path' => $data->idCardPhotoPath,
                    'selfie_photo_path' => $data->selfiePhotoPath,
                    'document_number_hash' => $data->documentNumber === null
                        ? null
                        : hash('sha256', $data->documentNumber),
                    'document_number_enc' => $data->documentNumber,
                    'name_on_document' => $data->nameOnDocument,
                    'birth_date_on_document' => $data->birthDateOnDocument,
                    // Diisi kalau nanti pakai layanan face-match; review manual dulu.
                    'face_match_score' => null,
                ];
            } else {
                $attributes += [
                    'bank_code' => $data->bankCode,
                    'account_number_enc' => $data->accountNumber,
                    'account_holder_name' => $data->accountHolderName,
                ];
            }

            // Pengajuan ulang menimpa yang belum final, tapi yang sudah
            // verified/rejected/revoked ditinggalkan sebagai riwayat —
            // riwayat penolakan itu sinyal penting.
            $pending = UserVerification::query()
                ->where('user_id', $user->getKey())
                ->where('type', $data->type)
                ->whereIn('status', [VerificationStatus::Pending, VerificationStatus::InReview])
                ->first();

            if ($pending !== null) {
                $pending->fill($attributes)->save();

                return $pending->refresh();
            }

            return UserVerification::query()->create($attributes);
        });
    }
}
