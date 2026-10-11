<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\Access\CreateAdminAction;
use App\Actions\Admin\Access\DeleteAdminAction;
use App\Data\Admin\CreateAdminData;
use App\Data\CursorPageData;
use App\Exceptions\Domain\DomainException;
use App\Http\Requests\Api\V1\Admin\CreateAdminRequest;
use App\Models\Admin;
use App\Support\Email;
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
            ->paginate(CursorPageData::fromRequest($request)->perPage)
            ->withQueryString();

        return view('super_admin.admins.index', ['admins' => $admins]);
    }

    public function store(Request $request, CreateAdminAction $action): RedirectResponse
    {
        // Validasi manual agar drawer kanan terbuka lagi beserta galatnya.
        // Aturannya DIAMBIL dari CreateAdminRequest, bukan disalin: dulu salinan
        // web hanya menuntut 12 karakter sementara API menuntut huruf besar,
        // angka, simbol, dan cek kebocoran — untuk akun yang sama.
        $rules = new CreateAdminRequest;
        $validator = Validator::make($request->all(), $rules->rules(), $rules->messages());

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->with('open_modal', 'create-admin');
        }

        $validated = $validator->validated();

        /** @var Admin $actor */
        $actor = Auth::guard('admin_web')->user();

        $data = new CreateAdminData($validated['name'], Email::normalize($validated['email']), $validated['password'], $request->ip());

        try {
            $admin = $action->handle($data, $actor);
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
