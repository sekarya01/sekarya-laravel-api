<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use App\Http\Requests\Api\V1\Wallet\ShowWalletSummaryRequest;
use Carbon\CarbonImmutable;

/**
 * Rentang ringkasan saldo.
 *
 * `from`/`to` disimpan DENGAN offset aslinya (untuk dikembalikan apa adanya
 * ke klien); Action yang mengubahnya ke UTC — zona kolom `created_at` —
 * saat menyusun kueri.
 */
final readonly class WalletSummaryQueryData
{
    public function __construct(
        /** Inklusif. */
        public CarbonImmutable $from,
        /** Eksklusif. */
        public CarbonImmutable $to,
        /** Zona waktu batas minggu pendapatan. */
        public string $timezone,
    ) {}

    /**
     * Tanpa `from`/`to`: bulan kalender berjalan di zona `tz` (bawaan: zona
     * aplikasi).
     */
    public static function fromRequest(ShowWalletSummaryRequest $request): self
    {
        $timezone = $request->filled('tz')
            ? $request->string('tz')->value()
            : (string) config('app.timezone');

        if ($request->filled('from') && $request->filled('to')) {
            return new self(
                from: CarbonImmutable::parse($request->string('from')->value()),
                to: CarbonImmutable::parse($request->string('to')->value()),
                timezone: $timezone,
            );
        }

        return self::currentMonth($timezone);
    }

    public static function currentMonth(string $timezone, ?CarbonImmutable $now = null): self
    {
        $start = ($now ?? CarbonImmutable::now())->setTimezone($timezone)->startOfMonth();

        return new self(from: $start, to: $start->addMonthNoOverflow(), timezone: $timezone);
    }
}
