<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Enums\PushType;
use App\Models\Activity;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;

/**
 * Satu-satunya tempat copy notifikasi push dan bentuk `data` disusun.
 *
 * Dipisah dari pengirim supaya mengubah kalimat atau menambah jenis peristiwa
 * tidak menyentuh transport, dan dari Action supaya aturan bisnis tidak
 * tercampur dengan urusan penyajian. Setiap jenis peristiwa punya satu fungsi
 * pabrik; `data` yang menyertainya lahir di SATU builder `data()` agar bentuk
 * deep-link hanya ditentukan sekali.
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
            title: $task->title,
            body: sprintf(
                '%s menawar sebesar Rp%s.',
                $bidder->name,
                self::rupiah($bid->amount),
            ),
            data: self::data(PushType::BidPlaced, $task, null, (int) $task->bids_count),
        );
    }

    /**
     * Pekerja diberi tahu penawarannya diterima.
     *
     * Judul notifikasi adalah judul task; isi mengabarkan peristiwanya saja.
     */
    public static function bidAccepted(Task $task, Bid $bid): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Penawaran Anda diterima.',
            data: self::data(PushType::BidAccepted, $task),
        );
    }

    /** Pekerja berangkat → pemberi kerja. */
    public static function activityOnTheWay(Task $task, Activity $activity): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Pekerja berangkat ke lokasi.',
            data: self::data(PushType::ActivityOnTheWay, $task, $activity),
        );
    }

    /** Kedatangan dikonfirmasi pemberi kerja → pekerja. */
    public static function activityArrived(Task $task, Activity $activity): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Kedatangan Anda dikonfirmasi.',
            data: self::data(PushType::ActivityArrived, $task, $activity),
        );
    }

    /** Pekerjaan mulai dikerjakan → pemberi kerja. */
    public static function activityInProgress(Task $task, Activity $activity): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Pekerjaan mulai dikerjakan.',
            data: self::data(PushType::ActivityInProgress, $task, $activity),
        );
    }

    /** Hasil dikirim pekerja → pemberi kerja. */
    public static function activitySubmitted(Task $task, Activity $activity): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Hasil dikirim, menunggu persetujuan.',
            data: self::data(PushType::ActivitySubmitted, $task, $activity),
        );
    }

    /** Hasil disetujui pemberi kerja → pekerja. */
    public static function activityApproved(Task $task, Activity $activity): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Hasil disetujui.',
            data: self::data(PushType::ActivityApproved, $task, $activity),
        );
    }

    /** Hasil ditolak pemberi kerja → pekerja. */
    public static function activityRejected(Task $task, Activity $activity): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Hasil ditolak, periksa catatannya.',
            data: self::data(PushType::ActivityRejected, $task, $activity),
        );
    }

    /**
     * SATU-SATUNYA pembentuk `data` FCM: `type` + `task_id` selalu ada,
     * `activity_id` + `activity_status` hanya ada bila activity diberikan,
     * `bids_count` hanya ada bila jumlah penawar diberikan (event lelang ke
     * pemberi kerja, agar kartu di list tugas bisa diperbarui langsung tanpa
     * refresh — nilainya sama dengan `bids_count` di TaskResource).
     *
     * Kunci yang kosong DIHILANGKAN (bukan `null`/`""`) karena nilai `data`
     * FCM wajib string. Layar detail di aplikasi yang memutuskan variannya
     * (Detail Tugas untuk pemberi kerja, Detail Kerjaan untuk mitra), jadi
     * tidak perlu rute berbeda per peran di sisi notifikasi.
     *
     * @return array<string, string>
     */
    private static function data(PushType $type, Task $task, ?Activity $activity = null, ?int $bidsCount = null): array
    {
        $data = [
            'type' => $type->value,
            'task_id' => (string) $task->ulid,
        ];

        if ($activity !== null) {
            $data['activity_id'] = (string) $activity->ulid;
            $data['activity_status'] = $activity->status->value;
        }

        if ($bidsCount !== null) {
            $data['bids_count'] = (string) $bidsCount;
        }

        return $data;
    }

    /** Rupiah tanpa desimal, titik sebagai pemisah ribuan — gaya aplikasi. */
    private static function rupiah(int $amount): string
    {
        return number_format((float) $amount, 0, ',', '.');
    }
}
