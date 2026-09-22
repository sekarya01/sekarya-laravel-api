<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Support\Push\PushMessage;
use App\Support\Push\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kirim satu notifikasi push, di luar siklus request.
 *
 * Dipisah ke antrean karena pengiriman menyentuh jaringan pihak ketiga: kalau
 * dikerjakan di dalam request, latensi Google menjadi latensi pengguna, dan
 * gangguan FCM bisa memperlambat aksi yang sebenarnya sudah berhasil.
 *
 * Payload-nya sudah berbentuk nilai siap kirim (bukan model yang perlu dimuat
 * ulang), jadi job ini tidak bergantung pada keadaan basis data saat ia
 * berjalan — cukup id penerima untuk menemukan perangkatnya.
 */
final class SendPushNotification implements ShouldQueue
{
    use Queueable;

    /** Gangguan FCM biasanya sementara; beberapa percobaan cukup. */
    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        private readonly int $userId,
        private readonly PushMessage $message,
    ) {}

    public function handle(PushNotifier $notifier): void
    {
        $user = User::query()->find($this->userId);

        // Penerima sudah dihapus sebelum job berjalan — tidak ada yang perlu
        // dikabari. Ini keadaan akhir yang sah, bukan kegagalan.
        if ($user === null) {
            return;
        }

        $notifier->send($user, $this->message);
    }
}
