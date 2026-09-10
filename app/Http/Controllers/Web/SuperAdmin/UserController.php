<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\User\ChangeUserStatusAction;
use App\Data\Admin\ChangeUserStatusData;
use App\Enums\AdminAction;
use App\Enums\Gender;
use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Exceptions\Domain\DomainException;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Moderasi pengguna.
 *
 * `email` adalah pencocokan PERSIS (kolom unique, terindeks) — bukan
 * pencarian sebagian. Proyek ini tidak memakai LIKE di mana pun.
 * Suspend/ban mencabut seluruh token (di dalam Action); reinstate akun yang
 * belum verifikasi email kembali ke pending_verification.
 *
 * Daftar memakai pagination BERNOMOR. Aturan saring & urut disalin dari
 * ListUsersAction — kalau Action itu berubah, samakan di sini.
 */
final class UserController
{
    public function index(Request $request): View|RedirectResponse
    {
        $request->validate([
            'status' => ['sometimes', 'nullable', 'string', 'max:30'],
            'email' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:20'],
            'ready' => ['sometimes', 'nullable', 'in:yes,no'],
            'ulid' => ['sometimes', 'nullable', 'string', 'size:26'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ]);

        // Lompat langsung ke detail lewat ULID — kunci publik yang terindeks,
        // berguna saat ULID disalin dari jejak audit atau laporan.
        if ($request->filled('ulid')) {
            $found = User::query()
                ->where('ulid', trim($request->string('ulid')->value()))
                ->first();

            if ($found instanceof User) {
                return redirect()->route('super_admin.users.show', $found);
            }

            return back()->withErrors(['ulid' => 'ULID tidak terdaftar.'])->withInput();
        }

        $gender = $request->filled('gender')
            ? Gender::tryFrom($request->string('gender')->value())
            : null;
        $ready = $request->string('ready')->value();

        $queue = User::query()
            ->when($request->filled('status'), fn ($q) => $q->where(
                'status', UserStatus::tryFrom($request->string('status')->value()),
            ))
            ->when(
                $request->filled('email'),
                fn ($q) => $q->where('email', mb_strtolower(trim($request->string('email')->value()))),
            )
            ->when($gender !== null, fn ($q) => $q->where('gender', $gender))
            // Kesiapan mengikuti definisi ready_to_work: profil ADA dan
            // identitas terverifikasi. "Belum" berarti salah satunya tidak ada.
            ->when($ready === 'yes', fn ($q) => $q
                ->whereHas('workerProfile')
                ->whereHas('verifications', fn ($v) => $v
                    ->where('type', VerificationType::Identity)
                    ->where('status', VerificationStatus::Verified)))
            ->when($ready === 'no', fn ($q) => $q
                ->where(fn ($w) => $w
                    ->whereDoesntHave('workerProfile')
                    ->orWhereDoesntHave('verifications', fn ($v) => $v
                        ->where('type', VerificationType::Identity)
                        ->where('status', VerificationStatus::Verified))))
            // Hitungan verifikasi identitas ikut termuat: penanda "siap kerja"
            // di daftar membacanya per baris, dan tanpa ini setiap baris
            // memicu satu kueri exists() tambahan.
            ->withCount([
                'verifications as identity_verified_count' => fn (Builder $v) => $v
                    ->where('type', VerificationType::Identity)
                    ->where('status', VerificationStatus::Verified),
            ])
            ->latestFirst()
            ->paginate(max(1, min($request->integer('per_page', 20), 50)))
            ->withQueryString();

        return view('super_admin.users.index', [
            'queue' => $queue,
            'filterStatus' => $request->string('status')->value() ?: '',
            'filterEmail' => $request->string('email')->value() ?: '',
            'filterGender' => $request->string('gender')->value() ?: '',
            'filterReady' => $ready,
        ]);
    }

    public function show(User $user): View
    {
        // Halaman ini konteks AKUN (profil + moderasi + riwayat verifikasi).
        // Konteks pekerja ada di halaman terpisah: WorkerController@show.
        $user->load(['verifications' => fn ($q) => $q->latest('id')]);

        return view('super_admin.users.show', ['user' => $user]);
    }

    public function suspend(Request $request, User $user, ChangeUserStatusAction $action): RedirectResponse
    {
        return $this->moderate($request, $user, $action, AdminAction::UserSuspended, 'suspend');
    }

    public function ban(Request $request, User $user, ChangeUserStatusAction $action): RedirectResponse
    {
        return $this->moderate($request, $user, $action, AdminAction::UserBanned, 'ban');
    }

    public function reinstate(Request $request, User $user, ChangeUserStatusAction $action): RedirectResponse
    {
        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $build = \Closure::bind(
                fn () => new ChangeUserStatusData(AdminAction::UserReinstated, null, $request->ip()),
                null,
                ChangeUserStatusData::class,
            );
            $action->handle($user, $admin, $build());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()])->with('open_modal', 'reinstate');
        }

        return redirect()->route('super_admin.users.show', $user)
            ->with('status', 'Akun dipulihkan.');
    }

    private function moderate(
        Request $request,
        User $user,
        ChangeUserStatusAction $action,
        AdminAction $act,
        string $kind,
    ): RedirectResponse {
        // Validasi manual agar popup yang benar bisa dibuka lagi beserta
        // galatnya — $request->validate() langsung melempar tanpa jejak
        // popup mana yang sedang dipakai.
        $validator = Validator::make(
            $request->all(),
            ['reason' => ['required', 'string', 'min:10', 'max:1000']],
            ['reason.min' => 'Jelaskan minimal 10 karakter supaya tercatat jelas di jejak audit.'],
        );

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('open_modal', $kind);
        }

        $validated = $validator->validated();

        /** @var Admin $admin */
        $admin = Auth::guard('admin_web')->user();

        try {
            $build = \Closure::bind(
                fn () => new ChangeUserStatusData($act, $validated['reason'], $request->ip()),
                null,
                ChangeUserStatusData::class,
            );
            $action->handle($user, $admin, $build());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()])->withInput()->with('open_modal', $kind);
        }

        $verb = $kind === 'ban' ? 'diblokir' : 'ditangguhkan';

        return redirect()->route('super_admin.users.show', $user)
            ->with('status', "Akun {$verb} dan seluruh tokennya dicabut.");
    }
}
