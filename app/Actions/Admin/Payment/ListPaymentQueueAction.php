<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Data\Admin\PaymentQueueData;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Antrean konfirmasi transfer. Paling lama menunggu di depan.
 *
 * Diurutkan `reported_at`, bukan `created_at`: baris pembayaran dibuat saat
 * pelamar pertama diterima, jauh sebelum ada transfer yang dilaporkan, jadi
 * `created_at` tidak mengatakan apa pun tentang berapa lama orang menunggu.
 * Ada indeks (status, reported_at) untuk urutan ini.
 */
final class ListPaymentQueueAction
{
    /** @return CursorPaginator<int, Payment> */
    public function handle(PaymentQueueData $data): CursorPaginator
    {
        return Payment::query()
            ->where('status', $data->status ?? PaymentStatus::AwaitingConfirmation)
            ->with(['task.category', 'payer'])
            // `id` ikut serta karena cursor pagination menuntut urutan yang
            // unik: dua laporan pada detik yang sama tanpa pemecah seri bisa
            // membuat satu baris terlewat atau terkirim dua kali antar halaman.
            ->orderBy('reported_at')
            ->orderBy('id')
            ->cursorPaginate($data->page->perPage);
    }
}
