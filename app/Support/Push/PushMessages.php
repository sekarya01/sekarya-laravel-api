<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Enums\PushType;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;

/**
 * Satu-satunya tempat copy notifikasi push disusun.
 *
 * Dipisah dari pengirim supaya mengubah kalimat atau menambah jenis peristiwa
 * tidak menyentuh transport, dan dari Action supaya aturan bisnis tidak
 * tercampur dengan urusan penyajian. Setiap jenis peristiwa punya satu fungsi
 * pabrik; `data` yang menyertainya juga lahir di sini agar bentuk deep-link
 * hanya ditentukan sekali.
 */
final class PushMessages
{
    /**
     * Pemberi kerja diberi tahu ada penawaran masuk.
     *
     * Nama penawar diambil dari objek yang sudah dipegang pemanggil, bukan
     * `$bid->bidder` — relasinya belum tentu termuat, dan memuatnya di sini
     * menambah satu kueri yang tidak perlu.
     */
    public static function bidPlaced(Task $task, Bid $bid, User $bidder): PushMessage
    {
        return new PushMessage(
            title: 'Penawaran baru',
            body: sprintf(
                '%s menawar "%s" sebesar Rp%s.',
                $bidder->name,
                $task->title,
                self::rupiah($bid->amount),
            ),
            data: self::taskData(PushType::BidPlaced, $task),
        );
    }

    /**
     * Pekerja diberi tahu penawarannya diterima.
     *
     * Menyebut "Detail Kerjaan" — istilah layar mitra — supaya yang menerima
     * tahu notifikasi ini mengantarnya ke pekerjaan, bukan sekadar membuka
     * tugas.
     */
    public static function bidAccepted(Task $task, Bid $bid): PushMessage
    {
        return new PushMessage(
            title: 'Penawaran diterima',
            body: sprintf(
                'Penawaran Anda untuk "%s" diterima. Ketuk untuk membuka detail kerjaan.',
                $task->title,
            ),
            data: self::taskData(PushType::BidAccepted, $task),
        );
    }

    /**
     * Deep-link yang sama untuk kedua peran: `task_id` publik (ULID).
     *
     * Layar detail di aplikasi yang memutuskan variannya (Detail Tugas untuk
     * pemberi kerja, Detail Kerjaan untuk mitra), jadi tidak perlu rute
     * berbeda per peran di sisi notifikasi.
     *
     * @return array<string, string>
     */
    private static function taskData(PushType $type, Task $task): array
    {
        return [
            'type' => $type->value,
            'task_id' => (string) $task->ulid,
        ];
    }

    /** Rupiah tanpa desimal, titik sebagai pemisah ribuan — gaya aplikasi. */
    private static function rupiah(int $amount): string
    {
        return number_format((float) $amount, 0, ',', '.');
    }
}
