<?php

declare(strict_types=1);

namespace App\Data\Admin;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Muatan penerbitan kode undangan mitra — dipakai API pengelola, dasbor, dan
 * CLI. Normalisasinya DI SINI (catatan/kota/provinsi di-trim, kosong = null),
 * bukan di tiap pemanggil: dulu dasbor men-trim catatan sementara API tidak.
 */
final readonly class CreateWorkerInviteCodeData
{
    public function __construct(
        public int $maxUses,
        public ?CarbonInterface $expiresAt = null,
        public ?string $note = null,
        public ?string $city = null,
        public ?string $province = null,
        public ?string $ip = null,
    ) {}

    /**
     * Dari nilai mentah (hasil validasi atau opsi CLI).
     *
     * @param  array{max_uses?: int|string|null, expires_at?: string|null, note?: string|null, city?: string|null, province?: string|null}  $values
     */
    public static function fromValues(array $values, ?string $ip = null): self
    {
        $expires = $values['expires_at'] ?? null;

        return new self(
            maxUses: max(1, (int) ($values['max_uses'] ?? 1)),
            expiresAt: $expires === null || $expires === '' ? null : Carbon::parse($expires),
            note: self::blankToNull($values['note'] ?? null),
            city: self::blankToNull($values['city'] ?? null),
            province: self::blankToNull($values['province'] ?? null),
            ip: $ip,
        );
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : $value;
    }
}
