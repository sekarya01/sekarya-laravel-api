<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\Wallet\ConfirmTopupAction;
use App\Actions\Admin\Wallet\RejectTopupAction;
use App\Data\Admin\RejectWalletRequestData;
use App\Enums\WalletTopupStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\Admin;
use App\Models\WalletTopup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Antrean isi saldo.
 *
 * Pengguna melapor sudah transfer dari aplikasi; saldonya baru bertambah saat
 * pengelola mengonfirmasi di sini, sesudah mencocokkan mutasi rekening.
 * Konfirmasi dan penolakan memakai Action yang sama dengan API admin
 * (`/admin/wallet/topups/{topup}/...`), termasuk jejak auditnya.
 *
 * Bawaan `awaiting_confirmation`, paling lama menunggu di depan — aturan yang
 * sama dengan ListTopupQueueAction. Pagination BERNOMOR.
 */
final class WalletTopupController
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'max:30'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $rawStatus = $request->string('status')->value() ?: WalletTopupStatus::AwaitingConfirmation->value;
        $status = WalletTopupStatus::tryFrom($rawStatus) ?? WalletTopupStatus::AwaitingConfirmation;

        $queue = WalletTopup::query()
            ->where('status', $status)
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(max(1, min($request->integer('per_page', 20), 50)))
            ->withQueryString();

        return view('super_admin.wallet_topups.index', [
            'queue' => $queue,
            'filterStatus' => $rawStatus,
        ]);
    }

    public function show(WalletTopup $topup): View
    {
        $topup->load('user');

        return view('super_admin.wallet_topups.show', [
            'topup' => $topup,
            'balance' => (int) $topup->user?->walletOrNew()->balance,
        ]);
    }

    public function confirm(WalletTopup $topup, ConfirmTopupAction $action, Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $action->handle($topup, $admin, $request->ip());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('super_admin.wallet_topups.show', $topup->ulid)
            ->with('status', 'Isi saldo dikonfirmasi. Saldo pengguna sudah bertambah.');
    }

    public function reject(Request $request, WalletTopup $topup, RejectTopupAction $action): RedirectResponse
    {
        $validated = $request->validate(
            ['reason' => ['required', 'string', 'min:10', 'max:500']],
            ['reason.min' => 'Jelaskan mutasi yang dicari supaya pengguna bisa memperbaiki laporannya.'],
        );

        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $action->handle($topup, $admin, new RejectWalletRequestData(trim($validated['reason']), $request->ip()));
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()])->withInput();
        }

        return redirect()->route('super_admin.wallet_topups.show', $topup->ulid)
            ->with('status', 'Isi saldo ditolak. Saldo pengguna tidak berubah.');
    }
}
