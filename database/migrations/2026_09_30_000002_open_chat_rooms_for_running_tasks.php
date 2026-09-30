<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\Chat\ChatRoomLifecycle;
use Illuminate\Database\Migrations\Migration;

/**
 * Migrasi DATA: room chat untuk task yang SUDAH berjalan sebelum chat ada.
 *
 * Room lahir saat DEAL (WorkOpening). Task yang deal sebelum rilis ini tidak
 * akan pernah melewati titik itu lagi, jadi pemberi kerja dan mitranya tidak
 * akan punya chat sampai tugasnya selesai. Hanya status yang masih bergerak
 * (`active`/`submitted`/`disputed`); task yang sudah berakhir tidak dibukakan
 * room — ia akan langsung `expired` dan kosong.
 *
 * Ikut `php artisan migrate` (server tanpa SSH, lihat migrasi
 * `..._open_stuck_dealt_tasks`). Aman diulang: `open()` idempoten.
 */
return new class extends Migration
{
    public function up(): void
    {
        $lifecycle = app(ChatRoomLifecycle::class);

        Task::query()
            ->whereIn('status', [TaskStatus::Active, TaskStatus::Submitted, TaskStatus::Disputed])
            ->whereHas('acceptedBids')
            ->whereDoesntHave('chatRoom')
            ->orderBy('id')
            ->each(fn (Task $task) => $lifecycle->open($task));
    }

    /** Room yang sudah berisi percakapan tidak dihapus demi riwayat migrasi. */
    public function down(): void
    {
        // sengaja kosong
    }
};
