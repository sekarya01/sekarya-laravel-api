<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use App\Http\Requests\Api\V1\Wallet\CreateWithdrawalRequest;

/**
 * Permintaan penarikan saldo.
 *
 * Hanya nominal. Rekening tujuannya TIDAK dikirim klien — ia diambil Action
 * dari verifikasi rekening yang sudah disetujui pengelola. Kalau tujuannya
 * bisa dikirim per permintaan, persetujuan rekening berhenti berarti apa pun:
 * saldo hasil kerja bisa dialirkan ke rekening mana saja yang belum pernah
 * dicocokkan dengan identitas pemiliknya.
 */
final readonly class CreateWithdrawalData
{
    public function __construct(public int $amount) {}

    public static function fromRequest(CreateWithdrawalRequest $request): self
    {
        return new self(amount: $request->integer('amount'));
    }
}
