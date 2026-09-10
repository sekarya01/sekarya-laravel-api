<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\Access\CreateAdminAction;
use App\Actions\Admin\Access\DeleteAdminAction;
use App\Actions\Admin\Access\ListAdminsAction;
use App\Data\Admin\CreateAdminData;
use App\Data\CursorPageData;
use App\Exceptions\Domain\DomainException;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Kelola akun pengelola. Peran baru SELALU `admin` (dipaksa di Action).
 * super_admin tidak bisa dihapus (hook `deleting` + unique index).
 * Hapus = soft delete; email tetap terpakai.
 */
final class AdminAccountController
{
    public function index(Request $request, ListAdminsAction $action): View
    {
        $admins = $action->handle(CursorPageData::fromRequest($request));

        return view('super_admin.admins.index', ['admins' => $admins]);
    }

    public function store(Request $request, CreateAdminAction $action): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            // Sama ketatnya seperti API: 12+, campur huruf/angka/simbol.
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        /** @var Admin $actor */
        $actor = Auth::guard('admin_web')->user();

        $build = \Closure::bind(
            fn () => new CreateAdminData($validated['name'], mb_strtolower(trim($validated['email'])), $validated['password'], $request->ip()),
            null,
            CreateAdminData::class,
        );

        try {
            $admin = $action->handle($build(), $actor);
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()])->withInput();
        }

        return redirect()->route('super_admin.admins.index')
            ->with('status', "Pengelola {$admin->email} dibuat sebagai admin.");
    }

    public function destroy(Request $request, Admin $admin, DeleteAdminAction $action): RedirectResponse
    {
        /** @var Admin $actor */
        $actor = Auth::guard('admin_web')->user();

        try {
            $action->handle($admin, $actor, $request->ip());
        } catch (DomainException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return redirect()->route('super_admin.admins.index')
            ->with('status', "Pengelola {$admin->email} dihapus (soft delete).");
    }
}
