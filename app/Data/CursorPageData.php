<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Http\Request;

/**
 * Parameter halaman untuk SEMUA endpoint daftar.
 *
 * Satu tempat untuk batas, default, dan clamping — sebelumnya diulang di
 * setiap DTO daftar dan setiap controller daftar.
 *
 * Clamping tetap dilakukan di sini meski rules sudah membatasi: DTO harus
 * aman saat Action dipanggil dari job atau command tanpa validasi HTTP.
 */
final readonly class CursorPageData
{
    public const int MAX_PER_PAGE = 50;

    public const int DEFAULT_PER_PAGE = 20;

    public function __construct(public int $perPage = self::DEFAULT_PER_PAGE) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            perPage: max(1, min(
                $request->integer('per_page', self::DEFAULT_PER_PAGE),
                self::MAX_PER_PAGE,
            )),
        );
    }

    /**
     * Aturan validasi yang dipakai bersama semua FormRequest daftar.
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
            // Cursor itu opaque — jangan pernah disusun manual oleh klien.
            'cursor' => ['sometimes', 'string', 'max:255'],
        ];
    }
}
