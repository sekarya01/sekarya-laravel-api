<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListWalletEntriesRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(WalletEntryType::class)],
            // Beberapa jenis sekaligus — satu kelompok riwayat di klien
            // (mis. "Isi saldo" = topup + refund + pembalikan) adalah
            // gabungan jenis, bukan satu jenis.
            'types' => ['sometimes', 'array', 'max:'.count(WalletEntryType::cases())],
            'types.*' => [Rule::enum(WalletEntryType::class)],
            'direction' => ['sometimes', Rule::enum(WalletEntryDirection::class)],
            // Kata dalam `description` ("Upah task #123").
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            // Rentang waktu ISO-8601 BEROFFSET dari klien: `from` inklusif,
            // `to` eksklusif. Hari "hari ini" milik orangnya (WIB), bukan
            // milik server (UTC) — offset itu yang membawanya.
            //
            // Pembanding ke ruas pasangan HANYA bila pasangannya dikirim:
            // `gte:min_amount` menolak `max_amount` sendirian (angka vs null
            // dianggap beda tipe), dan `after:from` membaca "from" sebagai
            // teks tanggal bila ruasnya tidak ada.
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', ...($this->filled('from') ? ['after:from'] : [])],
            'min_amount' => ['sometimes', 'integer', 'min:0'],
            'max_amount' => [
                'sometimes', 'integer', 'min:0',
                ...($this->filled('min_amount') ? ['gte:min_amount'] : []),
            ],
        ];
    }
}
