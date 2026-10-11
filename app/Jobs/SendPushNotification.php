<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Http\Resources\Api\V1\TaskDisputeResource;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Activity;
use App\Models\Task;
use App\Models\TaskDispute;
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
     * Plus snapshot sesuai event (lihat [PushSnapshot]): `task_update` (task
     * dari sudut PENERIMA tanpa pekerja/activity) atau `work_update` (satu
     * activity + sengketanya). Dibangun di sini, SESUDAH commit, supaya isinya
     * keadaan akhir — bukan saat push dijadwalkan di tengah transaksi.
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

        $request = Request::create('/');
        $request->setUserResolver(static fn () => $user);

        // Dua event yang berdiri sendiri (lihat PushMessages::data):
        //  - `work`: SATU activity (+ sengketanya) dan `task_status` — aplikasi
        //    mengganti baris pekerja itu saja.
        //  - `task`: task dari sudut penerima TANPA pekerja/activity — biayanya
        //    tetap berapa pun pekerjanya. Dulu detail LENGKAP dibangun per
        //    penerima lalu dibuang karena melebihi 4 KB.
        // Kunci lama `task` sengaja tidak dipakai: APK lama akan menimpa seluruh
        // task (dan daftar pekerjanya) dengan data parsial; tanpa kunci itu
        // APK lama memuat ulang dari API.
        $event = $data['event'] ?? (isset($data['activity_id']) ? 'work' : 'task');

        if ($event === 'work' && isset($data['activity_id'])) {
            $data = $this->attachWork($data, (string) $data['activity_id'], $request);
        } elseif ($taskId !== null) {
            $task = Task::query()->where('ulid', $taskId)->first();
            if ($task !== null) {
                $snapshot = TaskResource::make($task->loadSummaryFor($user))->resolve($request);
                $data = PushSnapshot::attach($data, 'task_update', $snapshot);
            }
        }

        return $this->message->withData($data, drawnByApp: true);
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private function attachWork(array $data, string $activityUlid, Request $request): array
    {
        $activity = Activity::query()
            ->where('ulid', $activityUlid)
            ->with(['worker' => fn ($q) => $q->withIdentityVerified(), 'payment', 'latestUpdate'])
            ->first();

        if ($activity === null) {
            return $data;
        }

        // Status task ikut berubah oleh agregat (TaskSettlement::sync), jadi
        // dibawa sebagai kunci tersendiri — bukan task bersarang.
        $taskStatus = Task::query()->whereKey($activity->task_id)->value('status');
        if ($taskStatus !== null) {
            $data['task_status'] = $taskStatus instanceof TaskStatus ? $taskStatus->value : (string) $taskStatus;
        }

        $work = ActivityResource::make($activity)->resolve($request);

        $dispute = TaskDispute::query()->where('activity_id', $activity->getKey())->latest('id')->first();
        if ($dispute !== null) {
            $work['dispute'] = TaskDisputeResource::make($dispute)->resolve($request);
        }

        return PushSnapshot::attach($data, 'work_update', $work);
    }
}
