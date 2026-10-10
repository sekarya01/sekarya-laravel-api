<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use App\Models\User;
use App\Support\Push\PushMessage;
use App\Support\Push\PushNotifier;
use App\Support\Push\PushSnapshot;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;

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

        $notifier->send($user, $this->withTaskSnapshot($user));
    }

    /**
     * Push TUGAS: dikirim data-only (`drawnByApp`) supaya APLIKASI yang
     * menggambar — satu notifikasi per tugas yang diganti tiap status berubah
     * (riwayat statusnya ditumpuk di dalamnya). Dengan blok `notification`,
     * sistem menggambar notifikasi BARU tiap push saat app di belakang.
     * `data` membawa `notify`/`title`/`body` untuk digambar.
     *
     * Plus tugas UTUH (bentuk `GET tasks/{task}`, dari sudut PENERIMA — lokasi
     * presisi, `my_bid`, jarak bergantung penonton) di `data.task`/`task_gz`
     * (lihat [PushSnapshot]). Dibangun di sini, SESUDAH commit, supaya isinya
     * keadaan akhir — bukan saat push dijadwalkan di tengah transaksi. Hanya
     * ke FCM: baris lonceng tetap `data` ramping.
     *
     * Chat & sinyal senyap dilewati (punya jalurnya sendiri); push tanpa
     * tugas atau tugas yang sudah terhapus = tetap data-only, tanpa snapshot.
     */
    private function withTaskSnapshot(User $user): PushMessage
    {
        $data = $this->message->data;
        $taskId = $data['task_id'] ?? null;
        $type = $data['type'] ?? '';
        if ($this->message->silent || str_starts_with($type, 'chat_')) {
            return $this->message;
        }

        // SEMUA push tampil non-chat data-only (2026-10-10), termasuk saldo &
        // penarikan tanpa tugas: aplikasi menyimpan riwayat lonceng di
        // perangkat, dan blok `notification` membuat Android menggambarnya
        // sendiri saat app di belakang — onMessageReceived tak pernah dipanggil.
        $data = [...$data, 'notify' => '1', 'title' => $this->message->title, 'body' => $this->message->body];
        $task = $taskId === null ? null : Task::query()->where('ulid', $taskId)->first();
        if ($task !== null) {
            $request = Request::create('/');
            $request->setUserResolver(static fn () => $user);
            $snapshot = TaskResource::make($task->loadDetailFor($user))->resolve($request);
            $data = PushSnapshot::attach($data, 'task', $snapshot);
        }

        return $this->message->withData($data, drawnByApp: true);
    }
}
