<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\Verification\ReviewVerificationAction;
use App\Actions\Admin\Verification\ViewVerificationAction;
use App\Data\Admin\ReviewVerificationData;
use App\Enums\AdminAction;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Exceptions\Domain\DomainException;
use App\Models\Admin;
use App\Models\UserVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Antrean verifikasi identitas & rekening.
 *
 * Daftar tidak memuat NIK / nomor rekening / path foto. Detail memanggil
 * ViewVerificationAction yang mencatat `verification.viewed` — setiap
 * pembukaan NIK meninggalkan jejak siapa, kapan, dari IP mana.
 *
 * Pagination BERNOMOR (bukan cursor seperti API): dasbor butuh lompat ke
 * halaman tertentu + tahu totalnya. Aturan saring & urutnya disalin dari
 * ListVerificationQueueAction — kalau Action itu berubah, samakan di sini.
 */
final class VerificationController
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['sometimes', 'string', 'max:30'],
            'type' => ['sometimes', 'string', 'max:30'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $rawStatus = $request->string('status')->value() ?: '__pending__';
        $status = $rawStatus === '__pending__' || $rawStatus === ''
            ? null
            : VerificationStatus::tryFrom($rawStatus);
        $type = $request->filled('type')
            ? VerificationType::tryFrom($request->string('type')->value())
            : null;

        $queue = UserVerification::query()
            ->when(
                $status !== null,
                fn ($q) => $q->where('status', $status),
                fn ($q) => $q->whereIn('status', [
                    VerificationStatus::Pending,
                    VerificationStatus::InReview,
                ]),
            )
            ->when($type !== null, fn ($q) => $q->where('type', $type))
            ->with('user')
            ->queueOrder()
            ->paginate(max(1, min($request->integer('per_page', 20), 50)))
            ->withQueryString();

        return view('super_admin.verifications.index', [
            'queue' => $queue,
            'filterStatus' => $rawStatus,
            'filterType' => $request->string('type')->value() ?: '',
        ]);
    }

    public function show(UserVerification $verification, ViewVerificationAction $action, Request $request): View
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        $verification = $action->handle($verification, $admin, $request->ip());

        return view('super_admin.verifications.show', ['verification' => $verification]);
    }

    public function approve(UserVerification $verification, ReviewVerificationAction $action, Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $action->handle($verification, $admin, ReviewVerificationData::approve($request));
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return $this->backTo($request, $verification, 'Verifikasi disetujui.');
    }

    public function reject(Request $request, UserVerification $verification, ReviewVerificationAction $action): RedirectResponse
    {
        $validated = $request->validate(
            ['reason' => ['required', 'string', 'min:10', 'max:500']],
            [
                'reason.required' => 'Sebutkan alasannya — pengguna berhak tahu apa yang harus diperbaiki.',
                'reason.min' => 'Alasan terlalu pendek untuk bisa dipahami penerimanya.',
            ],
        );

        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $action->handle($verification, $admin, $this->decision(
                VerificationStatus::Rejected,
                AdminAction::VerificationRejected,
                $validated['reason'],
                $request->ip(),
            ));
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()])->withInput();
        }

        return $this->backTo($request, $verification, 'Verifikasi ditolak. Alasan diteruskan ke pengguna.');
    }

    public function revoke(Request $request, UserVerification $verification, ReviewVerificationAction $action): RedirectResponse
    {
        $validated = $request->validate(
            ['reason' => ['required', 'string', 'min:10', 'max:500']],
            [
                'reason.required' => 'Sebutkan alasannya — pencabutan tanpa jejak alasan tidak bisa diaudit.',
                'reason.min' => 'Alasan terlalu pendek untuk bisa dipahami pemeriksa berikutnya.',
            ],
        );

        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $action->handle($verification, $admin, $this->decision(
                VerificationStatus::Revoked,
                AdminAction::VerificationRevoked,
                $validated['reason'],
                $request->ip(),
            ));
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()])->withInput();
        }

        return $this->backTo($request, $verification, 'Verifikasi dicabut.');
    }

    /**
     * Kembali ke halaman asal putusan — mis. halaman pekerja yang memuat
     * formulir putusan inline. Hanya path dasbor sendiri yang diterima,
     * supaya parameter ini tidak bisa jadi open redirect.
     */
    private function backTo(Request $request, UserVerification $verification, string $status): RedirectResponse
    {
        $to = $request->string('redirect_to')->value();

        if (str_starts_with($to, '/access/super_admin/')) {
            return redirect()->to($to)->with('status', $status);
        }

        return redirect()->route('super_admin.verifications.show', $verification)
            ->with('status', $status);
    }

    /**
     * Bentuk DTO yang sama seperti konstruktor bernama approve()/reject()/
     * revoke() — keputusan datang dari rute, bukan dari payload.
     */
    private function decision(
        VerificationStatus $decision,
        AdminAction $action,
        string $reason,
        ?string $ip,
    ): ReviewVerificationData {
        $build = \Closure::bind(
            fn () => new ReviewVerificationData($decision, $action, $reason, $ip),
            null,
            ReviewVerificationData::class,
        );

        return $build();
    }
}
