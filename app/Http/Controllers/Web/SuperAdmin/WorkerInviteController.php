<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\WorkerInvite\CreateWorkerInviteCodeAction;
use App\Actions\Admin\WorkerInvite\DeactivateWorkerInviteCodeAction;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Models\Admin;
use App\Models\WorkerInviteCode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Menu Kode Mitra: terbitkan kode undangan + lihat siapa memakainya.
 *
 * Kodenya dibuat acak oleh SERVER saat tombol ditekan (8 char string:
 * huruf kecil + KAPITAL + angka + special char) dan tampil TERUS di
 * daftar/detail supaya bisa dibagikan kapan saja — lihat catatan keputusan
 * di migrasi `2026_09_21_000002`.
 *
 * Halaman detail sekaligus meja verifikasi: daftar pekerja yang menukar kode
 * ini beserta status verifikasi identitasnya — yang belum terverifikasi
 * ditindaklanjuti di antrean verifikasi seperti biasa.
 */
final class WorkerInviteController
{
    public function index(Request $request): View
    {
        $request->validate([
            'usable' => ['sometimes', 'nullable', 'boolean'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // Select HTML selalu mengirim kuncinya (termasuk ''), jadi '' berarti
        // "tidak menyaring" — pola yang sama dengan direktori pekerja.
        $rawUsable = $request->string('usable')->value();
        $usable = $rawUsable === '' ? null : $request->boolean('usable');

        $codes = WorkerInviteCode::query()
            ->withCount('redemptions')
            ->with('creator')
            ->when($usable !== null, function (Builder $q) use ($usable): void {
                $q->where('is_active', $usable);
                if ($usable) {
                    // Bisa dipakai = aktif DAN kuota tersisa DAN tanggal belum lewat.
                    $q->whereColumn('used_count', '<', 'max_uses')
                        ->where(fn (Builder $w) => $w
                            ->whereNull('expires_at')
                            ->orWhere('expires_at', '>', now()));
                }
            })
            ->orderByDesc('id')
            ->paginate(max(1, min($request->integer('per_page', 20), 50)))
            ->withQueryString();

        return view('super_admin.worker_invites.index', [
            'codes' => $codes,
            'filterUsable' => $rawUsable,
        ]);
    }

    public function store(Request $request, CreateWorkerInviteCodeAction $action): RedirectResponse
    {
        // Masa hidup kode, dua pintu: jumlah max hit DAN/ATAU tanggal
        // kedaluwarsa — yang tercapai lebih dulu yang menutup. Max hit selalu
        // ada (penjaga utama); tanggal opsional. Validasi manual agar drawer
        // kanan terbuka lagi beserta galatnya.
        $validator = Validator::make(
            $request->all(),
            [
                'max_uses' => ['required', 'integer', 'min:1', 'max:100000'],
                'expires_at' => ['nullable', 'date', 'after:now'],
                'note' => ['nullable', 'string', 'max:255'],
                'city' => ['nullable', 'string', 'max:80'],
                'province' => ['nullable', 'string', 'max:80'],
            ],
            [
                'max_uses.required' => 'Jumlah max hit wajib diisi.',
                'max_uses.min' => 'Jumlah max hit minimal 1.',
                'expires_at.after' => 'Tanggal kedaluwarsa harus setelah sekarang.',
            ],
        );

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('open_modal', 'create-invite');
        }

        $validated = $validator->validated();

        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $result = $action->handle(
                (int) $validated['max_uses'],
                isset($validated['expires_at']) && $validated['expires_at'] !== null
                    ? Carbon::parse($validated['expires_at'])
                    : null,
                isset($validated['note']) && $validated['note'] !== '' ? trim($validated['note']) : null,
                $admin,
                $request->ip(),
                $validated['city'] ?? null,
                $validated['province'] ?? null,
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['action' => 'Gagal menerbitkan kode. Coba lagi.'])
                ->withInput()->with('open_modal', 'create-invite');
        }

        return redirect()->route('super_admin.worker_invites.show', $result['code']->getKey())
            ->with('status', "Kode {$result['plain']} diterbitkan dan tampil terus di halaman ini.");
    }

    public function show(WorkerInviteCode $code): View
    {
        $code->loadCount('redemptions')->load('creator');

        $redemptions = $code->redemptions()
            ->with(['user' => fn ($q) => $q->withCount([
                'verifications as identity_verified_count' => fn (Builder $v) => $v
                    ->where('type', VerificationType::Identity)
                    ->where('status', VerificationStatus::Verified),
            ])])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('super_admin.worker_invites.show', [
            'code' => $code,
            'redemptions' => $redemptions,
        ]);
    }

    public function deactivate(Request $request, WorkerInviteCode $code, DeactivateWorkerInviteCodeAction $action): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $action->handle($code, $admin, $request->ip());
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['action' => 'Gagal menonaktifkan kode. Coba lagi.']);
        }

        return redirect()->route('super_admin.worker_invites.show', $code->getKey())
            ->with('status', 'Kode dinonaktifkan. Pekerja yang sudah masuk lewat kode ini tetap mitra.');
    }
}
