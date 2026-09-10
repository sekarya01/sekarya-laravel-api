<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\AdminAction;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Bid;
use App\Models\Payment;
use App\Support\AdminAuditRecorder;
use App\Support\TaskStatusRecorder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Pengelola melihat dana masuk → dana ditahan → ACTIVITY DIBUKA.
 *
 * Inilah SATU-SATUNYA jalan menuju `held`, dan `held` adalah gerbang yang
 * membuka pekerjaan. Sebelumnya jalan itu dimiliki pemberi kerja sendiri
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
        private readonly TaskStatusRecorder $recorder,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** @return Collection<int, Activity> */
    public function handle(Payment $payment, Admin $admin, ?string $ip = null): Collection
    {
        return $this->db->transaction(function () use ($payment, $admin, $ip): Collection {
            // Row lock sungguhan di InnoDB: mencegah dua pengelola yang
            // membuka antrean yang sama sama-sama membuka activity. unique
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

            $task = $fresh->task;

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

            $now = now();

            $fresh->forceFill([
                'status' => PaymentStatus::Held,
                'paid_at' => $now,
                'held_at' => $now,
                'rejection_reason' => null,
            ])->save();

            // Baris activity inilah yang menegakkan aturan "tidak ada activity
            // tanpa dana ditahan" — bukan disiplin kode. Pembayarannya satu
            // untuk semua pekerja, jadi penjaganya unique (task_id, worker_id):
            // satu orang paling banyak satu activity per task, berapa kali pun
            // konfirmasi terpanggil.
            $activities = $task->acceptedBids()->get()->map(
                fn (Bid $bid): Activity => Activity::query()->firstOrCreate(
                    [
                        'task_id' => $task->getKey(),
                        'worker_id' => $bid->bidder_id,
                    ],
                    [
                        'payment_id' => $fresh->getKey(),
                        'status' => ActivityStatus::Open,
                        // Harga PER ORANG, dari penawarannya sendiri — bukan
                        // total task, yang pada task 30 orang akan membuat
                        // setiap pekerja terlihat berhak atas seluruh dana.
                        'agreed_amount' => $bid->amount,
                        'opened_at' => $now,
                    ],
                ),
            );

            $this->recorder->move(
                $task,
                TaskStatus::Active,
                // Pelakunya PENGELOLA, dan id-nya id dari tabel `admins`.
                // `task_status_logs` menyimpan pasangan actor_type + actor_id
                // tanpa foreign key justru untuk ini; tanpa actor_type yang
                // benar, id 7 di kolom itu akan terbaca sebagai pengguna 7.
                ActorType::Admin,
                $admin->getKey(),
                reason: sprintf('transfer dikonfirmasi, %d activity dibuka', $activities->count()),
            );

            $this->audit->record(
                $admin,
                AdminAction::PaymentConfirmed,
                (int) $fresh->getKey(),
                sprintf('Rp%s, %d activity', number_format((float) $fresh->amount, 0, ',', '.'), $activities->count()),
                $ip,
            );

            return $activities;
        });
    }
}
