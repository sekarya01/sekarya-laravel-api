<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\ConnectionInterface;

/**
 * Membuka pekerjaan yang terlanjur tersangkut di `dealt`.
 *
 * Sejak penutupan lelang sekaligus membuka pekerjaan, setiap deal punya
 * activity atas nama tiap pekerjanya. Task yang deal SEBELUM aturan itu tidak
 * punya — dan tidak akan pernah mendapatkannya sendiri, karena satu-satunya
 * jalan lain (konfirmasi pengelola atas transfer) menunggu mekanisme
 * pembayaran yang belum ada. Bagi pekerjanya, kerjaan yang sudah jadi miliknya
 * berhenti di "Bisa dimulai setelah pembayaran dikonfirmasi" selamanya.
 *
 * Jadi yang dikerjakan di sini bukan perbaikan data yang rusak, melainkan
 * menyusulkan baris yang seharusnya sudah ada sejak lelangnya ditutup.
 *
 * Dipanggil migrasi (`..._open_stuck_dealt_tasks`) supaya ikut jalan di
 * `php artisan migrate` — tanpa langkah manual yang bisa terlewat di server
 * tanpa SSH. Aman diulang: `WorkOpening` bersandar pada unique
 * (task_id, worker_id) dan tidak memindahkan task yang sudah `active`.
 */
final class StuckWorkBackfill
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly WorkOpening $opening,
    ) {}

    /**
     * @return list<int> id task yang dibuka
     */
    public function run(): array
    {
        $stuck = Task::query()
            ->where('status', TaskStatus::Dealt)
            ->whereDoesntHave('activities')
            ->whereHas('payment')
            ->whereHas('acceptedBids')
            ->orderBy('id')
            ->get();

        $opened = [];

        foreach ($stuck as $task) {
            $this->db->transaction(function () use ($task, &$opened): void {
                // Dikunci dan dibaca ulang di dalam transaksi: daftar di atas
                // bisa sudah basi kalau penerimaan penawaran lain berjalan
                // bersamaan.
                $fresh = Task::query()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();

                if ($fresh->status !== TaskStatus::Dealt || $fresh->activities()->exists()) {
                    return;
                }

                $this->opening->open(
                    $fresh,
                    $fresh->payment()->firstOrFail(),
                    // Pelakunya SISTEM, bukan pemberi kerjanya: yang membuka
                    // pekerjaan ini bukan keputusan siapa pun hari itu,
                    // melainkan penyusulan oleh aplikasi sendiri. Jejak yang
                    // menyebut pemberi kerja akan membuat seolah ia menekan
                    // sesuatu yang tidak pernah ia tekan.
                    ActorType::System,
                    null,
                    'pekerjaan yang tersangkut di dealt dibuka menyusul',
                );

                $opened[] = (int) $fresh->getKey();
            });
        }

        return $opened;
    }
}
