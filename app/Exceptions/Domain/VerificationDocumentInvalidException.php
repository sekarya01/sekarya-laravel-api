<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Path dokumen identitas bukan milik pengaju (G2a).
 *
 * `POST me/verifications` menerima PATH, bukan berkas. Tanpa pemeriksaan
 * kepemilikan, satu akun bisa melampirkan foto KTP milik akun lain sebagai
 * dokumennya sendiri — dan verifikasi identitas yang bisa dipalsukan lebih
 * buruk daripada tidak ada verifikasi sama sekali.
 */
final class VerificationDocumentInvalidException extends DomainException
{
    private function __construct(string $message, private readonly string $field)
    {
        parent::__construct($message);
    }

    public static function forField(string $field): self
    {
        return new self(
            "Dokumen pada {$field} tidak sah. Unggah lewat POST /me/verifications/documents.",
            $field,
        );
    }

    public function errorCode(): string
    {
        return 'verification_document_invalid';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['field' => $this->field];
    }
}
