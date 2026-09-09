<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\VerificationType;
use App\Http\Requests\Api\V1\User\SubmitVerificationRequest;

final readonly class SubmitVerificationData
{
    public function __construct(
        public VerificationType $type,
        // type = identity
        public ?string $idCardPhotoPath = null,
        public ?string $selfiePhotoPath = null,
        public ?string $documentNumber = null,
        public ?string $nameOnDocument = null,
        public ?string $birthDateOnDocument = null,
        // type = bank_account
        public ?string $bankCode = null,
        public ?string $accountNumber = null,
        public ?string $accountHolderName = null,
    ) {}

    public static function fromRequest(SubmitVerificationRequest $request): self
    {
        $str = fn (string $key): ?string => $request->filled($key)
            ? trim($request->string($key)->value())
            : null;

        return new self(
            type: VerificationType::from($request->string('type')->value()),
            idCardPhotoPath: $str('id_card_photo_path'),
            selfiePhotoPath: $str('selfie_photo_path'),
            // Dipisah dari path foto: yang ini akan di-hash DAN dienkripsi di Action.
            documentNumber: $str('document_number'),
            nameOnDocument: $str('name_on_document'),
            birthDateOnDocument: $str('birth_date_on_document'),
            bankCode: $str('bank_code'),
            accountNumber: $str('account_number'),
            accountHolderName: $str('account_holder_name'),
        );
    }
}
