<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\Payment\ConfirmPaymentAction;
use App\Actions\Admin\Payment\ListPaymentQueueAction;
use App\Actions\Admin\Payment\RejectPaymentAction;
use App\Data\Admin\PaymentQueueData;
use App\Data\Admin\RejectPaymentData;
use App\Data\CursorPageData;
use App\Enums\PaymentStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\Admin;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Antrean konfirmasi transfer.
 *
 * Bawaan `awaiting_confirmation`, urut `reported_at` (kapan pemberi kerja
 * mengaku transfer) — bukan `created_at`. Confirm adalah satu-satunya jalan
 * ke `held`, dan `held` membuka activity per pekerja yang diterima.
 */
final class PaymentController
{
    public function index(Request $request, ListPaymentQueueAction $action): View
    {
        $request->validate([
            'status' => ['sometimes', 'string', 'max:30'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $rawStatus = $request->string('status')->value() ?: PaymentStatus::AwaitingConfirmation->value;
        $status = PaymentStatus::tryFrom($rawStatus) ?? PaymentStatus::AwaitingConfirmation;

        $queue = $action->handle(new PaymentQueueData(
            page: CursorPageData::fromRequest($request),
            status: $status,
        ));

        return view('super_admin.payments.index', [
            'queue' => $queue,
            'filterStatus' => $rawStatus,
        ]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['task.category', 'payer']);

        return view('super_admin.payments.show', ['payment' => $payment]);
    }

    public function confirm(Payment $payment, ConfirmPaymentAction $action, Request $request): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $activities = $action->handle($payment, $admin, $request->ip());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('super_admin.payments.show', $payment)
            ->with('status', "Dana ditahan, {$activities->count()} activity dibuka.");
    }

    public function reject(Request $request, Payment $payment, RejectPaymentAction $action): RedirectResponse
    {
        $validated = $request->validate(
            ['reason' => ['required', 'string', 'min:10', 'max:500']],
            ['reason.min' => 'Jelaskan mutasi yang dicari supaya pemberi kerja bisa memperbaiki laporannya.'],
        );

        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $build = \Closure::bind(
                fn () => new RejectPaymentData($validated['reason'], $request->ip()),
                null,
                RejectPaymentData::class,
            );
            $action->handle($payment, $admin, $build());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()])->withInput();
        }

        return redirect()->route('super_admin.payments.show', $payment)
            ->with('status', 'Laporan ditolak. Tagihan kembali ke pending.');
    }
}
