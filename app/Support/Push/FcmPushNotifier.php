<?php

declare(strict_types=1);

namespace App\Support\Push;

use App\Exceptions\Push\FcmException;
use App\Exceptions\Push\InvalidDeviceTokenException;
use App\Models\User;
use Illuminate\Log\LogManager;

/**
 * Pengiriman push lewat FCM untuk seluruh perangkat seorang pengguna.
 *
 * Lapisan ini memutuskan KE PERANGKAT MANA pesan dikirim dan apa yang
 * dilakukan saat satu perangkat menolak. Transport-nya sendiri (token OAuth2,
 * panggilan HTTP, bentuk payload) ada di FcmClient.
 *
 * Best-effort: satu perangkat yang gagal tidak menghentikan pengiriman ke
 * perangkat lain, dan tidak pernah melempar ke pemanggil. Notifikasi adalah
 * efek samping dari aksi yang sudah berhasil — kegagalannya tidak boleh
 * membatalkan aksi itu.
 */
final class FcmPushNotifier implements PushNotifier
{
    public function __construct(
        private readonly FcmClient $client,
        private readonly LogManager $log,
    ) {}

    public function send(User $user, PushMessage $message): void
    {
        if (! $this->client->isEnabled()) {
            return;
        }

        foreach ($user->deviceTokens()->get() as $device) {
            try {
                $this->client->send($device->token, $message);
            } catch (InvalidDeviceTokenException) {
                // Token mati: disapu supaya tidak dicoba lagi selamanya.
                $device->delete();

                $this->log->channel('single')->info('[fcm] token perangkat disapu', [
                    'user_id' => $user->getKey(),
                    'device_id' => $device->getKey(),
                ]);
            } catch (FcmException $e) {
                // Gangguan sementara (kuota, jaringan, kredensial). Dicatat
                // tanpa token perangkat — nilai rahasia itu tidak pernah
                // masuk log.
                $this->log->channel('single')->warning('[fcm] pengiriman gagal', [
                    'user_id' => $user->getKey(),
                    'device_id' => $device->getKey(),
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }
}
