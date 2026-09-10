<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\Verification\ViewVerificationAction;
use App\Enums\Gender;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Admin;
use App\Models\UserWorker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Direktori pekerja (sisi `user_workers`).
 *
 * Filter & urutnya mengikuti ListWorkersAction: hanya akun `active` yang
 * muncul; gender, kota/provinsi teresolusi; terbaru siap bekerja dulu.
 * `ready_to_work` adalah penyaring OPSIONAL dua arah (penanda, bukan gerbang).
 */
final class WorkerController
{
    public function index(Request $request): View
    {
        $request->validate([
            'ready_to_work' => ['sometimes', 'nullable', 'boolean'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'province' => ['sometimes', 'nullable', 'string', 'max:80'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // Select HTML selalu mengirim kuncinya (termasuk ''), jadi '' berarti
        // "tidak menyaring" — berbeda dari API yang memakai has(). Nilai '0'
        // harus terbaca sebagai false yang disengaja, bukan sebagai kosong.
        $rawReady = $request->string('ready_to_work')->value();
        $readyToWork = $rawReady === '' ? null : $request->boolean('ready_to_work');
        $gender = $request->filled('gender')
            ? Gender::tryFrom($request->string('gender')->value())
            : null;

        $workers = UserWorker::query()
            ->whereHas('user', function (Builder $u) use ($readyToWork, $gender): void {
                // Basisnya SELALU akun aktif, seperti API. Moderasi akun yang
                // ditangguhkan lewat halaman Pengguna, yang memang untuk itu.
                $u->where('status', UserStatus::Active);

                // Penanda, bukan gerbang: hanya berlaku kalau diminta.
                if ($readyToWork !== null) {
                    $readyToWork
                        ? $u->whereHas('verifications', fn (Builder $v) => $v
                            ->where('type', VerificationType::Identity)
                            ->where('status', VerificationStatus::Verified))
                        : $u->whereDoesntHave('verifications', fn (Builder $v) => $v
                            ->where('type', VerificationType::Identity)
                            ->where('status', VerificationStatus::Verified));
                }

                if ($gender !== null) {
                    $u->where('gender', $gender);
                }
            })
            ->when(
                $request->filled('city'),
                fn (Builder $q) => $q->whereResolvedAddress('city', trim($request->string('city')->value())),
            )
            ->when(
                $request->filled('province'),
                fn (Builder $q) => $q->whereResolvedAddress('province', trim($request->string('province')->value())),
            )
            ->with(['user' => fn (Relation $q) => $q
                ->with('skills')
                ->withCount([
                    'verifications as identity_verified_count' => fn (Builder $v) => $v
                        ->where('type', VerificationType::Identity)
                        ->where('status', VerificationStatus::Verified),
                ])])
            ->latestFirst()
            ->paginate(max(1, min($request->integer('per_page', 20), 50)))
            ->withQueryString();

        return view('super_admin.workers.index', [
            'workers' => $workers,
            'filterReadyToWork' => $rawReady,
            'filterGender' => $request->string('gender')->value() ?: '',
            'filterCity' => $request->string('city')->value() ?: '',
            'filterProvince' => $request->string('province')->value() ?: '',
        ]);
    }

    /**
     * Detail satu pekerja — SEKALIGUS meja verifikasi identitasnya.
     *
     * Konteks KERJA, bukan akun: identitas teresolusi, alamat kerja, titik &
     * radius, reputasi, keahlian, checklist kesiapan — plus putusan
     * verifikasi identity (setujui/tolak/cabut) langsung dari sini.
     * Moderasi akun dan antrean umum verifikasi tetap di tempatnya
     * masing-masing.
     *
     * Kalau pengajuan identity-nya masih menunggu, pembukaannya dicatat
     * `verification.viewed` lewat ViewVerificationAction — SAMA seperti
     * halaman detail verifikasi, karena NIK-nya ikut tampil di sini.
     */
    public function show(Request $request, UserWorker $worker, ViewVerificationAction $viewAction): View
    {
        $worker->load([
            'user.skills',
            'user.verifications' => fn ($q) => $q->latest('id'),
        ]);

        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        $identity = $worker->user?->verifications
            ->firstWhere('type', VerificationType::Identity);

        if ($identity !== null && $identity->status->awaitsReview()) {
            $identity = $viewAction->handle($identity, $admin, $request->ip());
        }

        return view('super_admin.workers.show', [
            'worker' => $worker,
            'identity' => $identity,
        ]);
    }
}
