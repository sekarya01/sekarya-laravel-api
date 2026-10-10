<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Jobs\SendPushNotification;
use Illuminate\Support\Facades\DB;

/**
 * SATU-SATUNYA pintu keluar push.
 *
 * Job FCM diantrekan SESUDAH commit (`afterCommit`): push tidak bisa ditarik
 * kembali, jadi ia baru berangkat setelah aksinya pasti tersimpan.
 *
 * Server tidak menyimpan kotak masuk notifikasi (dihapus 2026-10-10, tabel
 * `user_notifications` di-drop): riwayat lonceng milik perangkat. Akibatnya
 * push yang gagal atau pengguna tanpa perangkat terdaftar tidak meninggalkan
 * jejak di server — itu disengaja.
 */
final class PushDispatcher
{
    /**
     * Push notifikasi (lewat antrean, sesudah commit). Server TIDAK menyimpan
     * salinan apa pun (keputusan produk 2026-10-10): riwayat lonceng disimpan
     * aplikasi di perangkat dari push ini.
     */
    public function send(int $userId, PushMessage $message): void
    {
        SendPushNotification::dispatch($userId, $message)->afterCommit();
    }

    /**
     * Push chat — realtime, tanpa antrean.
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
