<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Http\Requests\Api\V1\Wallet\ShowWalletSummaryRequest;
use Carbon\CarbonImmutable;

/**
 * Rentang dan penyaring ringkasan saldo (B2).
 *
 * `from`/`to` disimpan DENGAN offset aslinya (untuk dikembalikan apa adanya
 * ke klien); Action yang mengubahnya ke UTC — zona kolom `created_at` —
 * saat menyusun kueri.
 */
final readonly class WalletSummaryQueryData
{
    /**
     * @param  list<WalletEntryType>  $types
     */
    public function __construct(
        /** Inklusif. */
        public CarbonImmutable $from,
        /** Eksklusif. */
        public CarbonImmutable $to,
        public array $types = [],
        public ?WalletEntryDirection $direction = null,
        public bool $comparePrevious = false,
        public bool $groupByMonth = false,
    ) {}

    /**
     * Tanpa `from`/`to`: bulan kalender berjalan di zona aplikasi.
     */
    public static function fromRequest(ShowWalletSummaryRequest $request): self
    {
        $range = $request->filled('from') && $request->filled('to')
            ? [
                CarbonImmutable::parse($request->string('from')->value()),
                CarbonImmutable::parse($request->string('to')->value()),
            ]
            : self::currentMonth();

        /** @var list<WalletEntryType> $types */
        $types = array_map(
            static fn (string $value): WalletEntryType => WalletEntryType::from($value),
            array_values((array) $request->input('types', [])),
        );

        return new self(
            from: $range[0],
            to: $range[1],
            types: $types,
            direction: $request->filled('direction')
                ? WalletEntryDirection::from($request->string('direction')->value())
                : null,
            comparePrevious: $request->boolean('compare_previous'),
            groupByMonth: $request->string('group')->value() === 'month',
        );
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function currentMonth(?CarbonImmutable $now = null): array
    {
        $start = ($now ?? CarbonImmutable::now())
            ->setTimezone((string) config('app.timezone'))
            ->startOfMonth();

        return [$start, $start->addMonthNoOverflow()];
    }
}
