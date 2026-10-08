<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\Activity;
use Carbon\CarbonInterface;

/**
 * Persetujuan otomatis hasil yang tak kunjung dikonfirmasi.
 *
 * Pemberi kerja yang 24 jam (lihat `sekarya.activities.auto_approve_hours`)
 * tidak menyetujui/menolak hasil yang sudah diserahkan dianggap setuju:
 * pekerjanya dibayar sesuai janji lewat `ApproveActivityAction` yang sama
 * dengan tombol konfirmasi — tidak ada logika uang kedua.
 *
 * Idempoten: hanya menyentuh activity `submitted` yang `submitted_at`-nya
 * lewat; balapan dengan poster/admin yang menekan di detik yang sama
 * dimenangkan siapa pun yang tercatat lebih dulu (pecundang mendapat
 * `DomainException` dan dilewati). Task `disputed`/final tidak disentuh.
 */
final class AutoApproveStaleActivitiesAction
{
    public function __construct(
        private readonly ApproveActivityAction $approve,
    ) {}

    /** @return int Jumlah activity yang disetujui otomatis. */
    public function handle(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $cutoff = $now->copy()->subHours((int) config('sekarya.activities.auto_approve_hours', 24));
        $approved = 0;

        Activity::query()
            ->where('status', ActivityStatus::Submitted)
            ->whereNotNull('submitted_at')
            ->where('submitted_at', '<=', $cutoff)
            // Ter-cover index (status, submitted_at); saring task yang tak
            // boleh selesai otomatis: sengketa didahulukan, status akhir
            // tak bisa bergerak lagi.
            ->whereHas('task', fn ($query) => $query->whereNotIn('status', [
                TaskStatus::Completed,
                TaskStatus::Expired,
                TaskStatus::Cancelled,
                TaskStatus::Disputed,
                TaskStatus::Refunded,
            ]))
            ->orderBy('id')
            ->chunkById(100, function ($activities) use (&$approved): void {
                foreach ($activities as $activity) {
                    $poster = $activity->task?->poster;

                    if ($poster === null) {
                        continue;
                    }

                    try {
                        $this->approve->handle($activity, $poster, null, ActorType::System);
                    } catch (DomainException) {
                        continue;
                    }

                    $approved++;
                }
            });

        return $approved;
    }
}
