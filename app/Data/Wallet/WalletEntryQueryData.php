<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use App\Data\CursorPageData;
use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Http\Requests\Api\V1\Wallet\ListWalletEntriesRequest;

/** Penyaring riwayat mutasi saldo. Keduanya opsional. */
final readonly class WalletEntryQueryData
{
    public function __construct(
        public CursorPageData $page,
        public ?WalletEntryType $type = null,
        public ?WalletEntryDirection $direction = null,
    ) {}

    public static function fromRequest(ListWalletEntriesRequest $request): self
    {
        return new self(
            page: $request->page(),
            type: $request->filled('type')
                ? WalletEntryType::from($request->string('type')->value())
                : null,
            direction: $request->filled('direction')
                ? WalletEntryDirection::from($request->string('direction')->value())
                : null,
        );
    }
}
