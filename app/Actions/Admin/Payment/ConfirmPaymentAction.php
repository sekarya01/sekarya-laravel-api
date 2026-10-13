<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Enums\ActorType;
use App\Enums\AdminAction;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\Task;
use App\Support\AdminAuditRecorder;
use App\Support\Rupiah;
use App\Support\WorkOpening;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Pengelola melihat dana masuk → dana ditahan → activity yang belum ada dibuka.
 *
 * Jalur transfer manual LAMA, hanya untuk tagihan task yang dibuat sebelum
 * TaskEscrow (task baru lahir `held` dari saldo). Untuk tagihan itu inilah
 * SATU-SATUNYA jalan menuju `held`, dan `held` adalah gerbang yang membolehkan
 * pekerjaan DIMULAI (`StartActivityAction`). Sebelumnya jalan itu dimiliki pemberi kerja sendiri
 * (endpoint `payment/hold`), yang berarti pernyataan "saya sudah transfer"
 * dan fakta "dananya ada" adalah hal yang sama. Sekarang tidak.
 *
 * Satu task bisa merekrut banyak orang, jadi satu konfirmasi membuka BANYAK
 * activity — satu per pekerja yang diterima, masing-masing membawa harga
 * penawarannya sendiri. Tagihannya tetap satu baris sebesar jumlah semuanya,
 * karena pemberi kerja mentransfer sekali.
 */
final class ConfirmPaymentAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly WorkOpening $opening,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /**
     * [#impure] [#io: db, fcm] [#tx: own] [#lock: tasks → payments]
     * [#warn: jalur lama — hanya tagihan sebelum TaskEscrow]
     *
     * @return Collection<int, Activity>
     */
    public function handle(Payment $payment, Admin $admin, ?string $ip = null): Collection
    {
        return $this->db->transaction(function () use ($payment, $admin, $ip): Collection {
            // Task dikunci LEBIH DULU, baru tagihan — urutan yang sama dengan
            // CancelTaskAction. Urutan terbalik antara dua jalur = deadlock;
            // tanpa kunci task, status yang diperiksa di bawah bisa basi.
            $task = Task::query()->whereKey($payment->task_id)->lockForUpdate()->firstOrFail();

            // Row lock sungguhan di InnoDB: mencegah dua pengelola yang
            // membuka antrean yang sama sama-sama menahan dana. unique
            // (task_id, worker_id) di activities tetap jadi pengaman terakhir —
            // lock hanya berlaku di dalam transaksi, dan jalur tulis lain
            // belum tentu mengambilnya.
            $fresh = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo(PaymentStatus::Held)) {
                throw InvalidStatusTransitionException::between(
                    $fresh->status->value,
                    PaymentStatus::Held->value,
                );
            }

            // Perekrutan harus sudah ditutup. Diperiksa ulang di sini, bukan
            // hanya saat laporan masuk: yang mengubah status task adalah
            // permintaan lain, dan urutan kedatangan dua permintaan bukan
            // sesuatu yang bisa diandalkan.
            if ($task->status->acceptsBids()) {
                throw InvalidStatusTransitionException::between(
                    $task->status->value,
                    TaskStatus::Active->value,
                );
            }

            $fresh->forceFill([
                'status' => PaymentStatus::Held,
                'paid_at' => now(),
                'held_at' => now(),
                'rejection_reason' => null,
            ])->save();

            // Activity biasanya SUDAH dibuka saat deal (perekrutan ditutup);
            // di sini hanya disusulkan untuk task warisan yang barisnya belum
            // ada. Pembayarannya satu untuk semua pekerja, jadi penjaganya
            // unique (task_id, worker_id): satu orang paling banyak satu
            // activity per task, berapa kali pun konfirmasi terpanggil.
            //
            // Pembukaannya sendiri dipinjam dari WorkOpening, karena jalur
            // deal harus membuka pekerjaan dengan cara yang sama persis.
            $activities = $this->opening->open(
                $task,
                $fresh,
                // Pelakunya PENGELOLA, dan id-nya id dari tabel `admins`.
                // `task_status_logs` menyimpan pasangan actor_type + actor_id
                // tanpa foreign key justru untuk ini; tanpa actor_type yang
                // benar, id 7 di kolom itu akan terbaca sebagai pengguna 7.
                ActorType::Admin,
                $admin->getKey(),
                sprintf('transfer dikonfirmasi, %d activity dibuka', $task->acceptedBids()->count()),
            );

            $this->audit->record(
                $admin,
                AdminAction::PaymentConfirmed,
                (int) $fresh->getKey(),
                sprintf('%s, %d activity', Rupiah::format($fresh->amount), $activities->count()),
                $ip,
            );

            return $activities;
        });
    }
}
