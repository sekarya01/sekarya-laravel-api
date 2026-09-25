<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use App\Data\CursorPageData;
use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Http\Requests\Api\V1\Wallet\ListWalletEntriesRequest;
use Carbon\CarbonImmutable;

/** Penyaring riwayat mutasi saldo. Semuanya opsional. */
final readonly class WalletEntryQueryData
{
    /**
     * @param  list<WalletEntryType>  $types  kosong = semua jenis
     */
    public function __construct(
        public CursorPageData $page,
        public ?WalletEntryType $type = null,
        public ?WalletEntryDirection $direction = null,
        public array $types = [],
        public ?string $search = null,
        /** Inklusif, UTC. */
        public ?CarbonImmutable $from = null,
        /** Eksklusif, UTC. */
        public ?CarbonImmutable $to = null,
        public ?int $minAmount = null,
        public ?int $maxAmount = null,
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
            types: array_values(array_map(
                WalletEntryType::from(...),
                array_unique($request->array('types')),
            )),
            search: $request->filled('q') ? trim($request->string('q')->value()) : null,
            from: self::instant($request, 'from'),
            to: self::instant($request, 'to'),
            minAmount: $request->filled('min_amount') ? $request->integer('min_amount') : null,
            maxAmount: $request->filled('max_amount') ? $request->integer('max_amount') : null,
        );
    }

    /** Waktu klien (beroffset) → UTC, zona kolom `created_at`. */
    private static function instant(ListWalletEntriesRequest $request, string $key): ?CarbonImmutable
    {
        return $request->filled($key)
            ? CarbonImmutable::parse($request->string($key)->value())->utc()
            : null;
    }
}
