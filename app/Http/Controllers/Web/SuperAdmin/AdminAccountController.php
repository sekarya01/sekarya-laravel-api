<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\Access\CreateAdminAction;
use App\Actions\Admin\Access\DeleteAdminAction;
use App\Data\Admin\CreateAdminData;
use App\Exceptions\Domain\DomainException;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Kelola akun pengelola. Peran baru SELALU `admin` (dipaksa di Action).
 * super_admin tidak bisa dihapus (hook `deleting` + unique index).
 * Hapus = soft delete; email tetap terpakai.
 */
final class AdminAccountController
{
    public function index(Request $request): View
    {
        // Pagination BERNOMOR; urutannya sama seperti ListAdminsAction.
        $admins = Admin::query()
            ->latestFirst()
            ->paginate(max(1, min($request->integer('per_page', 20), 50)))
            ->withQueryString();

        return view('super_admin.admins.index', ['admins' => $admins]);
    }

    public function store(Request $request, CreateAdminAction $action): RedirectResponse
    {
        // Validasi manual agar drawer kanan terbuka lagi beserta galatnya.
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            // Sama ketatnya seperti API: 12+, campur huruf/angka/simbol.
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('open_modal', 'create-admin');
        }

        $validated = $validator->validated();

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
            return back()->withErrors(['action' => $e->getMessage()])->withInput()->with('open_modal', 'create-admin');
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
