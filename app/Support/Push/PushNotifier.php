<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Models\User;

/**
 * Kontrak pengiriman push.
 *
 * Action dan job berbicara ke antarmuka ini, bukan ke FCM. Dengan begitu
 * pengiriman nyata bisa diganti tanpa menyentuh aturan bisnis, dan test bisa
 * memakai implementasi palsu tanpa jaringan maupun kredensial.
 *
 * Implementasi WAJIB bersifat best-effort: kegagalan mengirim notifikasi
 * tidak boleh menggagalkan aksi yang sudah berhasil (penawaran tetap sah
 * walau pemberi kerja tidak sempat menerima pemberitahuannya).
 */
interface PushNotifier
{
    public function send(User $user, PushMessage $message): void;
}
