<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang dasbor web /access/super_admin.
 *
 * Hanya `super_admin` yang boleh masuk — bukan sekadar `admin` yang aktif.
 * Alasannya: dasbor ini memuat kelola akun pengelola (buat/hapus admin),
 * yang di API dijaga middleware `admin.manages-admins`. Menyatukannya di
 * satu gerbang membuat seluruh dasbor membaca satu aturan.
 *
 * Status akun diperiksa per permintaan (seperti `admin.active` di API):
 * sesi tidak menyimpan status, dan sesi hidup lebih lama dari keputusan
 * suspend.
 */
final class EnsureSuperAdminWeb
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin_web')->user();

        if (! $admin instanceof Admin) {
            if ($request->expectsJson()) {
                abort(Response::HTTP_UNAUTHORIZED, 'Belum masuk sebagai pengelola.');
            }

            return redirect()->route('super_admin.login');
        }

        if (! $admin->status->isActive() || ! $admin->isSuperAdmin()) {
            Auth::guard('admin_web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(Response::HTTP_FORBIDDEN, 'Hanya super_admin yang boleh membuka dasbor ini.');
            }

            return redirect()->route('super_admin.login')
                ->withErrors(['email' => 'Akun ini tidak berhak membuka dasbor super admin.']);
        }

        return $next($request);
    }
}
