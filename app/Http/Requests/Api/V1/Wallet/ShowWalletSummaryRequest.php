<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Enums\WalletSummaryGroup;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `GET me/wallet/summary` (B2).
 *
 * Rentang `from`/`to` sama persis dengan penyaring `me/wallet/entries`:
 * ISO-8601 beroffset, `from` inklusif, `to` eksklusif — supaya total di atas
 * daftar menjumlahkan persis baris yang tampil di bawahnya. Penyaring
 * `types[]`/`direction` diterima agar totalnya bisa mengikuti tab yang sedang
 * dibuka, bukan selalu seluruh riwayat.
 */
final class ShowWalletSummaryRequest extends FormRequest
{
    /** Rentang terpanjang yang boleh dijumlahkan sekali panggil. */
    public const int MAX_RANGE_DAYS = 366;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Berpasangan. Satu batas saja tidak punya arti yang jelas untuk
            // sebuah total ("sejak kapan sampai kapan?"), dan menebaknya
            // diam-diam menghasilkan angka yang terlihat benar untuk rentang
            // yang salah.
            //
            // Tanpa `sometimes`: aturan itu melewatkan ruas yang tidak
            // dikirim, termasuk `required_with` — sehingga `from` sendirian
            // lolos.
            //
            // `group=day|week` mewajibkan rentang: deretnya diisi nol untuk
            // setiap hari/pekan, jadi ujungnya harus disebut klien.
            'from' => ['required_with:to', 'required_if:group,day,week', 'date'],
            'to' => [
                'required_with:from',
                'required_if:group,day,week',
                'date',
                ...($this->filled('from') ? ['after:from'] : []),
            ],
            // Penyaring yang sama dengan `me/wallet/entries`, supaya total
            // mengikuti tab: `direction` memisah masuk/keluar, `types[]`
            // memilih beberapa jenis sekaligus.
            'types' => ['sometimes', 'array', 'min:1', 'max:'.count(WalletEntryType::cases())],
            'types.*' => ['distinct', Rule::enum(WalletEntryType::class)],
            'direction' => ['sometimes', Rule::enum(WalletEntryDirection::class)],
            // "Pendapatan minggu ini vs pekan lalu": satu panggilan bisa
            // membawa periode sebelumnya yang panjangnya SAMA, tepat sebelum
            // rentang ini.
            'compare_previous' => ['sometimes', 'boolean'],
            // Deret per hari/pekan/bulan untuk grafik (Pemasukan, Riwayat).
            'group' => ['sometimes', Rule::enum(WalletSummaryGroup::class)],
            // Rincian pendapatan per kategori task (layar Pemasukan).
            'with_categories' => ['sometimes', 'boolean'],
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
