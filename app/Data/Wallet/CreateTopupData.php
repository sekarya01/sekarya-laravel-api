<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use App\Http\Requests\Api\V1\Wallet\CreateTopupRequest;

/**
 * Permintaan isi saldo.
 *
 * Tidak ada `status` di sini, dan itu bukan kelalaian: status permintaan
 * ditentukan Action, bukan pengirimnya. Field `status` di DTO ini akan
 * menjadikan `POST /me/wallet/topups` jalan mengisi saldo tanpa transfer,
 * dan yang menahannya cuma aturan validasi.
 */
final readonly class CreateTopupData
{
    public function __construct(
        public int $amount,
        public ?string $senderNote = null,
    ) {}

    public static function fromRequest(CreateTopupRequest $request): self
    {
        $note = trim($request->string('sender_note')->value());

        return new self(
            amount: $request->integer('amount'),
            senderNote: $note === '' ? null : $note,
        );
    }
}
