<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Jobs\SendPushNotification;
use App\Models\UserNotification;

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
}
