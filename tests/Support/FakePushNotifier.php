<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Support\Push\PushMessage;
use App\Support\Push\PushNotifier;

/**
 * Pengirim push palsu — merekam, tidak mengirim.
 *
 * Dipakai test alur notifikasi: yang diuji adalah "siapa menerima pesan apa",
 * bukan jaringan FCM. Karena `QUEUE_CONNECTION=sync` di suite, job pengiriman
 * berjalan langsung di dalam request, jadi rekaman ini terisi tanpa worker.
 */
final class FakePushNotifier implements PushNotifier
{
    /** @var list<array{user: User, message: PushMessage}> */
    public array $sent = [];

    public function send(User $user, PushMessage $message): void
    {
        $this->sent[] = ['user' => $user, 'message' => $message];
    }

    /** Berapa pesan yang ditujukan ke penerima tertentu (berdasarkan id). */
    public function countTo(User $user): int
    {
        return count(array_filter(
            $this->sent,
            static fn (array $entry): bool => $entry['user']->getKey() === $user->getKey(),
        ));
    }

    /** Pesan pertama untuk penerima tertentu, atau null. */
    public function firstTo(User $user): ?PushMessage
    {
        foreach ($this->sent as $entry) {
            if ($entry['user']->getKey() === $user->getKey()) {
                return $entry['message'];
            }
        }

        return null;
    }
}
