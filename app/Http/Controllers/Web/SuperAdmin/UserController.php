<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\User\ChangeUserStatusAction;
use App\Actions\Admin\User\ListUsersAction;
use App\Data\Admin\ChangeUserStatusData;
use App\Data\Admin\UserQueueData;
use App\Data\CursorPageData;
use App\Enums\AdminAction;
use App\Enums\UserStatus;
use App\Exceptions\Domain\DomainException;
use App\Models\Admin;
use App\Models\User;
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
 */
final class UserController
{
    public function index(Request $request, ListUsersAction $action): View
    {
        $request->validate([
            'status' => ['sometimes', 'string', 'max:30'],
            'email' => ['sometimes', 'string', 'max:255'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $queue = $action->handle(new UserQueueData(
            page: CursorPageData::fromRequest($request),
            status: $request->filled('status') ? UserStatus::tryFrom($request->string('status')->value()) : null,
            email: $request->filled('email') ? mb_strtolower(trim($request->string('email')->value())) : null,
        ));

        return view('super_admin.users.index', [
            'queue' => $queue,
            'filterStatus' => $request->string('status')->value() ?: '',
            'filterEmail' => $request->string('email')->value() ?: '',
        ]);
    }

    public function show(User $user): View
    {
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
