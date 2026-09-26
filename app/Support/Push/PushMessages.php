<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Enums\PushType;
use App\Models\Activity;
use App\Models\Bid;
use App\Models\Task;
use App\Models\TaskCancelRequest;
use App\Models\User;
use App\Models\WalletTopup;
use App\Models\WalletWithdrawal;

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
     * Pemberi kerja meminta pembatalan → setiap pekerja yang harus menjawab.
     *
     * `cancel_request_id` ikut supaya popup di Detail Kerjaan bisa langsung
     * memanggil approve/reject tanpa membaca `GET tasks/{task}` lebih dulu.
     */
    public static function cancelRequested(Task $task, TaskCancelRequest $request): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Pemberi kerja meminta pembatalan. Setujui atau tolak.',
            data: self::data(PushType::CancelRequested, $task, extra: [
                'cancel_request_id' => (string) $request->ulid,
            ]),
        );
    }

    /**
     * Permintaan pembatalan selesai dijawab → pemberi kerja.
     *
     * `result` = `approved` (semua pekerja setuju, task dibatalkan) atau
     * `rejected` (satu pekerja menolak, task berjalan terus).
     */
    public static function cancelRequestResolved(Task $task, TaskCancelRequest $request, bool $approved): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: $approved
                ? 'Permintaan pembatalan disetujui. Tugas dibatalkan.'
                : 'Permintaan pembatalan ditolak. Tugas tetap berjalan.',
            data: self::data(PushType::CancelRequestResolved, $task, extra: [
                'cancel_request_id' => (string) $request->ulid,
                'result' => $approved ? 'approved' : 'rejected',
            ]),
        );
    }

    /** Task dibatalkan → pekerja yang sudah diterima. */
    public static function taskCancelled(Task $task): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Tugas ini dibatalkan.',
            data: self::data(PushType::TaskCancelled, $task),
        );
    }

    /** Tugas baru tayang → mitra tersedia di sekitar lokasi (B13). */
    public static function taskPublished(Task $task): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Tugas baru di sekitar Anda.',
            data: self::data(PushType::TaskPublished, $task),
        );
    }

    /** Batas waktu penawaran lewat → pemberi kerja (G12). */
    public static function taskExpired(Task $task): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Batas waktu penawaran terlewat. Tugas kedaluwarsa.',
            data: self::data(PushType::TaskExpired, $task),
        );
    }

    /** Penawaran gugur karena lelang ditutup → penawar (G12). */
    public static function bidExpired(Task $task): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: 'Batas waktu penawaran terlewat. Penawaran Anda tidak lagi diproses.',
            data: self::data(PushType::BidExpired, $task),
        );
    }

    /** Sengketa diputuskan → kedua pihak (G5). */
    public static function disputeResolved(Task $task, bool $released): PushMessage
    {
        return new PushMessage(
            title: $task->title,
            body: $released
                ? 'Sengketa diputuskan: dana dilepas ke pekerja.'
                : 'Sengketa diputuskan: dana dikembalikan ke pemberi kerja.',
            data: self::data(PushType::DisputeResolved, $task, extra: [
                'resolution' => $released ? 'release' : 'refund',
            ]),
        );
    }

    /** Pengelola melihat dananya → saldo bertambah (G11). */
    public static function topupConfirmed(WalletTopup $topup): PushMessage
    {
        return new PushMessage(
            title: 'Isi saldo dikonfirmasi',
            body: 'Saldo Rp'.self::rupiah((int) $topup->amount).' sudah masuk.',
            data: self::walletData(PushType::TopupConfirmed, (int) $topup->getKey(), (int) $topup->amount),
        );
    }

    /** Dana tidak ditemukan di mutasi → permintaan isi saldo ditolak (G11). */
    public static function topupRejected(WalletTopup $topup): PushMessage
    {
        return new PushMessage(
            title: 'Isi saldo ditolak',
            body: 'Permintaan isi saldo Rp'.self::rupiah((int) $topup->amount).' ditolak. Periksa alasannya.',
            data: self::walletData(PushType::TopupRejected, (int) $topup->getKey(), (int) $topup->amount),
        );
    }

    /** Pengelola sudah mentransfer ke rekening → penarikan selesai (G11). */
    public static function withdrawalCompleted(WalletWithdrawal $withdrawal): PushMessage
    {
        return new PushMessage(
            title: 'Penarikan selesai',
            body: 'Rp'.self::rupiah((int) $withdrawal->amount).' sudah dikirim ke rekening Anda.',
            data: self::walletData(PushType::WithdrawalCompleted, (int) $withdrawal->getKey(), (int) $withdrawal->amount),
        );
    }

    /** Penarikan ditolak → tahanannya dikembalikan ke saldo (G11). */
    public static function withdrawalRejected(WalletWithdrawal $withdrawal): PushMessage
    {
        return new PushMessage(
            title: 'Penarikan ditolak',
            body: 'Rp'.self::rupiah((int) $withdrawal->amount).' dikembalikan ke saldo. Periksa alasannya.',
            data: self::walletData(PushType::WithdrawalRejected, (int) $withdrawal->getKey(), (int) $withdrawal->amount),
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
     * `extra` untuk kunci khas satu jenis peristiwa (mis. `cancel_request_id`,
     * `result`); nilainya wajib string, sama seperti kunci lain.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private static function data(
        PushType $type,
        Task $task,
        ?Activity $activity = null,
        ?int $bidsCount = null,
        array $extra = [],
    ): array {
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

        return [...$data, ...$extra];
    }

    /** Rupiah tanpa desimal, titik sebagai pemisah ribuan — gaya aplikasi. */
    private static function rupiah(int $amount): string
    {
        return number_format((float) $amount, 0, ',', '.');
    }

    /**
     * Bentuk `data` untuk peristiwa DOMPET (G11).
     *
     * Berbeda dari `data()` yang selalu membawa `task_id`: permintaan isi
     * saldo/penarikan tidak melekat pada task mana pun, jadi yang dikirim
     * adalah id permintaannya dan nominalnya. Aplikasi memakainya untuk
     * membuka layar Saldo dan menyorot baris yang berubah.
     *
     * @return array<string, string>
     */
    private static function walletData(PushType $type, int $requestId, int $amount): array
    {
        return [
            'type' => $type->value,
            'wallet_request_id' => (string) $requestId,
            'amount' => (string) $amount,
        ];
    }
}
