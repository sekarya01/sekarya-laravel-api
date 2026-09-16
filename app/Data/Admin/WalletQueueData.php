<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Data\CursorPageData;
use App\Http\Requests\Api\V1\Admin\WalletTopupQueueRequest;
use App\Http\Requests\Api\V1\Admin\WalletWithdrawalQueueRequest;

/**
 * Penyaring antrean saldo — dipakai topup maupun penarikan.
 *
 * Statusnya disimpan sebagai string, bukan sebagai salah satu dari dua enum:
 * DTO yang membawa dua jenis enum sekaligus akan menuntut setiap pembacanya
 * mencabang pada jenisnya. Yang memastikan nilainya sah adalah FormRequest
 * masing-masing (`Rule::enum`), yang memang tahu enum mana yang berlaku di
 * antreannya.
 */
final readonly class WalletQueueData
{
    public function __construct(
        public CursorPageData $page,
        public ?string $status = null,
    ) {}

    public static function fromRequest(
        WalletTopupQueueRequest|WalletWithdrawalQueueRequest $request,
    ): self {
        return new self(
            page: $request->page(),
            status: $request->filled('status')
                ? $request->string('status')->value()
                : null,
        );
    }
}
