<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use App\Models\UserVerification;
use App\Models\UserWorker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Ringkasan dasbor: angka antrean + aktivitas terbaru.
 *
 * Hanya membaca hitungan (query COUNT), bukan daftar — halaman ini tidak
 * memegang data sensitif apa pun (tanpa NIK, tanpa nomor rekening).
 */
final class DashboardController
{
    public function __invoke(): View
    {
        $pendingVerifications = UserVerification::query()
            ->whereIn('status', [VerificationStatus::Pending, VerificationStatus::InReview])
            ->count();

        $awaitingPayments = Payment::query()
            ->where('status', PaymentStatus::AwaitingConfirmation)
            ->count();

        $activeUsers = User::query()->where('status', UserStatus::Active)->count();
        $totalAdmins = Admin::query()->count();

        $recentAudits = AdminAuditLog::query()
            ->with('admin')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $openTasks = Task::query()->where('status', TaskStatus::Open)->count();

        // Gerbang yang sama seperti ready_to_work: profil ADA dan identitas
        // terverifikasi. Menghitung baris user_workers saja akan melebihkan —
        // profil tanpa persetujuan pengelola bukan "siap kerja".
        $readyWorkers = UserWorker::query()
            ->whereHas('user', fn (Builder $q) => $q
                ->where('status', UserStatus::Active)
                ->whereHas('verifications', fn (Builder $v) => $v
                    ->where('type', VerificationType::Identity)
                    ->where('status', VerificationStatus::Verified)))
            ->count();

        return view('super_admin.dashboard', [
            'pendingVerifications' => $pendingVerifications,
            'awaitingPayments' => $awaitingPayments,
            'activeUsers' => $activeUsers,
            'totalAdmins' => $totalAdmins,
            'openTasks' => $openTasks,
            'readyWorkers' => $readyWorkers,
            'recentAudits' => $recentAudits,
        ]);
    }
}
