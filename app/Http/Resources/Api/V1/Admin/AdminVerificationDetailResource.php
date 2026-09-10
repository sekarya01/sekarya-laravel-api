<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Enums\VerificationType;
use App\Http\Resources\Api\V1\BaseResource;
use App\Models\UserVerification;
use Illuminate\Http\Request;

/**
 * SATU-SATUNYA tempat di seluruh API yang mengeluarkan NIK dan nomor
 * rekening dalam bentuk terbaca.
 *
 * Ini keputusan sadar, bukan kelalaian — DAN KEPUTUSAN PEMILIK PROYEK, diambil
 * secara eksplisit setelah alternatifnya ditawarkan (masker sebagian, atau
 * tidak ditampilkan sama sekali). Pekerjaan yang diminta dari pengelola adalah
 * memastikan nomor pada dokumen cocok dengan yang diketik pemiliknya; tanpa
 * nomornya, "verifikasi identitas" hanya bisa dijawab dengan menebak.
 *
 * Jangan menutupinya "supaya lebih aman" tanpa menanyakan ulang: yang hilang
 * bukan kenyamanan, melainkan satu-satunya cara pekerjaan ini bisa dikerjakan.
 *
 * Yang membuatnya bisa dipertanggungjawabkan, dan ketiganya harus tetap ada:
 *
 *  1. Hanya di endpoint DETAIL, satu baris per permintaan — bukan di antrean.
 *  2. Setiap pembacaan MENULIS jejak (`verification.viewed` di
 *     `admin_audit_logs`): siapa membuka data siapa, kapan, dari IP mana.
 *     Keputusan bisa ditinjau dari statusnya; pembacaan tidak meninggalkan
 *     bekas apa pun kalau tidak dicatat.
 *  3. Tidak ikut ke log mana pun. AxiomRequestLogger hanya menyertakan body
 *     respons untuk status >= 400, jadi respons 200 ini tidak pernah terkirim
 *     ke luar proses — dan itu invarian yang dijaga test, bukan kebetulan.
 *
 * Yang TETAP tidak keluar: `document_number_hash` (bahan deteksi duplikat,
 * tidak ada gunanya bagi manusia) dan path foto — foto dibuka lewat signed
 * URL yang memverifikasi pembacanya, dan endpoint itu belum ada.
 *
 * @mixin UserVerification
 */
final class AdminVerificationDetailResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $isIdentity = $this->type === VerificationType::Identity;

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'awaits_review' => $this->status->awaitsReview(),

            'has_id_card_photo' => $this->id_card_photo_path !== null,
            'has_selfie_photo' => $this->selfie_photo_path !== null,
            'face_match_score' => $this->face_match_score === null
                ? null
                : (float) $this->face_match_score,

            // --- Identitas ---
            'name_on_document' => $this->when($isIdentity, fn (): ?string => $this->name_on_document),
            'birth_date_on_document' => $this->when(
                $isIdentity,
                fn (): ?string => $this->birth_date_on_document?->toDateString(),
            ),
            // Cast `encrypted` yang mendekripsinya. Kolomnya sendiri tetap
            // terenkripsi di basis data dan di setiap backup.
            'document_number' => $this->when($isIdentity, fn (): ?string => $this->document_number_enc),

            // --- Rekening ---
            'bank_code' => $this->when(! $isIdentity, fn (): ?string => $this->bank_code),
            'account_holder_name' => $this->when(! $isIdentity, fn (): ?string => $this->account_holder_name),
            'account_number' => $this->when(! $isIdentity, fn (): ?string => $this->account_number_enc),

            'rejection_reason' => $this->rejection_reason,
            'revoked_reason' => $this->revoked_reason,
            'submitted_at' => $this->iso($this->submitted_at),
            'reviewed_at' => $this->iso($this->reviewed_at),
            'reviewed_by' => AdminResource::make($this->whenLoaded('reviewer')),
            'user' => AdminUserResource::make($this->whenLoaded('user')),
        ];
    }
}
