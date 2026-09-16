<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet;
use Illuminate\Http\Request;

/**
 * Saldo milik sendiri.
 *
 * `id` bisa `null`, dan itu disengaja: jalur baca tidak membuat baris dompet
 * (lihat `User::walletOrNew()`), jadi orang yang belum pernah menerima atau
 * mengisi apa pun mendapat bentuk respons yang SAMA dengan yang sudah punya —
 * bersaldo nol. Klien tidak perlu punya dua cabang untuk satu layar.
 *
 * @mixin Wallet
 */
final class WalletResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            // Bilangan bulat, satuan terkecil — seperti seluruh kolom uang di
            // API ini. Pemformatan rupiah urusan klien.
            'balance' => (int) $this->balance,
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
