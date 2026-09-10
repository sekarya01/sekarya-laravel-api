<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use Database\Factories\UserVerificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UserVerification extends Model
{
    /** @use HasFactory<UserVerificationFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'type', 'status',
        'id_card_photo_path', 'selfie_photo_path', 'face_match_score',
        'document_number_hash', 'document_number_enc',
        'name_on_document', 'birth_date_on_document',
        'bank_code', 'account_number_enc', 'account_holder_name',
        'submitted_at',
    ];

    /**
     * Kolom foto dan NIK TIDAK boleh ikut serialisasi default.
     * Path foto hanya dibuka lewat signed URL, NIK hanya saat sengketa.
     */
    protected $hidden = [
        'id_card_photo_path', 'selfie_photo_path',
        'document_number_hash', 'document_number_enc', 'account_number_enc',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => VerificationType::class,
            'status' => VerificationStatus::class,
            'face_match_score' => 'decimal:2',
            'birth_date_on_document' => 'date',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'revoked_at' => 'datetime',
            'document_number_enc' => 'encrypted',
            'account_number_enc' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pengelola yang menilai. `reviewed_by` menunjuk `admins`, bukan `users` —
     * dijamin foreign key sejak migrasi
     * `link_verification_reviewer_to_admins`.
     *
     * @return BelongsTo<Admin, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    /**
     * Antrean penilaian: yang belum final, paling lama menunggu di depan.
     *
     * Urutannya `submitted_at` lalu `id` — dua baris yang diajukan pada detik
     * yang sama tidak boleh menghasilkan urutan yang berbeda antar halaman,
     * yang pada cursor pagination berarti baris terlewat atau terkirim dua
     * kali.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeQueueOrder(Builder $query): void
    {
        $query->orderBy('submitted_at')->orderBy('id');
    }
}
