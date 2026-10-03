<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Jobs\SendPushNotification;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA pintu keluar notifikasi: kotak masuk in-app DAN push.
 *
 * Dua hal dikerjakan berurutan, dan urutannya disengaja:
 *
 *  1. Baris `user_notifications` ditulis LANGSUNG, di koneksi yang sama
 *     dengan Action pemanggil. Kalau Action itu sedang di dalam transaksi,
 *     barisnya ikut transaksi: aksi yang batal tidak meninggalkan notifikasi
 *     palsu di lonceng.
 *  2. Job FCM diantrekan SESUDAH commit (`afterCommit`). Push tidak bisa
 *     ditarik kembali, jadi ia baru berangkat setelah aksinya pasti tersimpan.
 *
 * Karena baris ditulis sebelum dan terlepas dari job, push yang gagal, FCM
 * yang dimatikan (`firebase.enabled=false`), atau pengguna tanpa perangkat
 * terdaftar TETAP meninggalkan barisnya — lonceng tidak bergantung pada
 * Google. Sebaliknya tidak berlaku: tidak ada push tanpa baris, karena tidak
 * ada jalur lain yang mengantrekan `SendPushNotification`.
 */
final class PushDispatcher
{
    public function send(int $userId, PushMessage $message): UserNotification
    {
        $notification = UserNotification::query()->create([
            'user_id' => $userId,
            // `type` di data adalah kontrak deep-link (PushType); kolomnya
            // menyalin nilai yang sama supaya klien membaca satu kosakata.
            'type' => $message->data['type'] ?? 'general',
            'title' => $message->title,
            'body' => $message->body,
            'data' => $message->data === [] ? null : $message->data,
        ]);

        SendPushNotification::dispatch($userId, $message)->afterCommit();

        return $notification;
    }

    /**
     * Push TANPA baris kotak masuk — khusus chat.
     *
     * Pesan chat dan sinyal sinkronnya bukan notifikasi lonceng: satu
     * percakapan bisa menghasilkan ratusan, dan riwayatnya sudah ada di
     * `chat_messages`. Tetap lewat kelas ini supaya tetap hanya ada SATU
     * tempat yang mengirim `SendPushNotification`, dan tetap sesudah commit.
     *
     * Chat harus REALTIME, jadi job ini TIDAK lewat antrean: di hosting
     * bersama antrean diproses cron per menit (`queue:work --stop-when-empty`,
     * docs/DEPLOYMENT.md §8) — pesan baru sampai ke lawan bicara terlambat
     * hingga ±1 menit, atau tidak pernah bila cron pekerja mati. Ia dijalankan
     * di proses yang sama SESUDAH respons HTTP terkirim
     * (`dispatchAfterResponse` → fastcgi/litespeed_finish_request), sehingga
     * pengirim tidak ikut menunggu FCM. Konsekuensinya tanpa percobaan ulang
     * antrean; aplikasi menutupnya dengan sinkron berkala selama chat terbuka.
     */
    public function sendTransient(int $userId, PushMessage $message): void
    {
        DB::afterCommit(static fn () => SendPushNotification::dispatchAfterResponse($userId, $message));
    }
}
