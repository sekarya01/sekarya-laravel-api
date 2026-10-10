<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\Dispute\ResolveDisputeAction;
use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\Admin;
use App\Models\TaskDispute;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Antrean sengketa per mitra.
 *
 * Pemberi kerja menyengketakan hasil satu mitra dari aplikasi; mitra boleh
 * menanggapi sekali. Pengelola menimbang keduanya — bukti foto, jam tiba &
 * selesai di activity — lalu memutuskan di sini dengan keterangan WAJIB yang
 * dikirim ke kedua pihak. Keputusan memakai Action yang sama dengan API admin
 * (`/admin/disputes/{dispute}/resolve`), termasuk jejak auditnya.
 *
 * Bawaan `open`, paling lama menunggu di depan. Pagination BERNOMOR.
 */
final class DisputeController
{
    public function index(Request $request): View
    {
        $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'max:16'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $status = DisputeStatus::tryFrom($request->string('status')->value()) ?? DisputeStatus::Open;

        $queue = TaskDispute::query()
            ->where('status', $status)
            ->with(['task.poster', 'activity.worker'])
            ->when($status === DisputeStatus::Open, fn ($q) => $q->orderBy('created_at')->orderBy('id'))
            ->when($status !== DisputeStatus::Open, fn ($q) => $q->orderByDesc('resolved_at')->orderByDesc('id'))
            ->paginate(max(1, min($request->integer('per_page', 20), 50)))
            ->withQueryString();

        return view('super_admin.disputes.index', [
            'queue' => $queue,
            'filterStatus' => $status->value,
        ]);
    }

    public function show(TaskDispute $dispute): View
    {
        $dispute->load(['task.poster', 'activity.worker', 'resolver']);

        return view('super_admin.disputes.show', ['dispute' => $dispute]);
    }

    public function resolve(Request $request, TaskDispute $dispute, ResolveDisputeAction $action): RedirectResponse
    {
        $validated = $request->validate(
            [
                'resolution' => ['required', Rule::enum(DisputeResolution::class)],
                'note' => ['required', 'string', 'min:10', 'max:500'],
            ],
            ['note.required' => 'Tulis keterangan — dikirim ke pemberi kerja dan mitra.'],
        );

        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $action->handle(
                $dispute,
                $admin,
                DisputeResolution::from($validated['resolution']),
                trim($validated['note']),
                $request->ip(),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()])->withInput();
        }

        return redirect()->route('super_admin.disputes.show', $dispute->ulid)
            ->with('status', 'Sengketa diputuskan. Pemberi kerja dan mitra sudah diberi tahu.');
    }
}
