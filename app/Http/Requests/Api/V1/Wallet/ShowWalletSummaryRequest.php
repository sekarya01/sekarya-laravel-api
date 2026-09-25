<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ShowWalletSummaryRequest extends FormRequest
{
    /** Rentang terpanjang yang boleh dijumlahkan sekali panggil. */
    public const int MAX_RANGE_DAYS = 366;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Sama dengan penyaring `me/wallet/entries`: ISO-8601 BEROFFSET,
            // `from` inklusif, `to` eksklusif — supaya total di atas daftar
            // menjumlahkan persis baris yang tampil di bawahnya.
            //
            // Berpasangan. Satu batas saja tidak punya arti yang jelas untuk
            // sebuah total ("sejak kapan sampai kapan?"), dan menebaknya diam-diam
            // menghasilkan angka yang terlihat benar untuk rentang yang salah.
            //
            // Tanpa `sometimes`: aturan itu melewatkan ruas yang tidak dikirim,
            // termasuk `required_with` — sehingga `from` sendirian lolos.
            'from' => ['required_with:to', 'date'],
            'to' => ['required_with:from', 'date', ...($this->filled('from') ? ['after:from'] : [])],
            // Zona waktu yang dipakai untuk rentang BAWAAN (bulan berjalan) dan
            // untuk batas minggu `earnings_this_week`/`earnings_last_week`.
            // Bawaannya zona aplikasi; klien di Indonesia sebaiknya mengirim
            // `Asia/Jakarta` supaya "minggu ini" dimulai Senin 00:00 WIB.
            'tz' => ['sometimes', 'string', 'timezone:all'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->filled('from') || ! $this->filled('to')) {
                    return;
                }

                $from = CarbonImmutable::parse($this->string('from')->value());
                $to = CarbonImmutable::parse($this->string('to')->value());

                if ($from->diffInDays($to, true) > self::MAX_RANGE_DAYS) {
                    $validator->errors()->add(
                        'to',
                        sprintf('Rentang ringkasan paling panjang %d hari.', self::MAX_RANGE_DAYS),
                    );
                }
            },
        ];
    }
}
