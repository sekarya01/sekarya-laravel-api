<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Actions\Admin\Auth\VerifyAdminCredentialsAction;
use App\Data\Admin\AdminLoginData;
use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Support\Email;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Masuk/keluar dasbor super_admin (sesi, bukan token API).
 *
 * Kredensial diperiksa VerifyAdminCredentialsAction — pemeriksa yang SAMA
 * dengan login API (hash dummy anti-enumerasi, status SETELAH sandi benar).
 * Di atasnya hanya peran super_admin yang boleh masuk — admin biasa tetap
 * lewat API.
 */
final class AuthController
{
    public function showLogin(): View
    {
        if (Auth::guard('admin_web')->check()) {
            abort(302, '', ['Location' => route('super_admin.dashboard')]);
        }

        return view('super_admin.login');
    }

    /** [#impure] [#io: db] [#warn: recordLogin + sesi hanya sesudah gerbang super_admin] */
    public function login(Request $request, VerifyAdminCredentialsAction $credentials): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        try {
            $admin = $credentials->handle(new AdminLoginData(
                Email::normalize($validated['email']),
                $validated['password'],
                $request->ip(),
            ));
        } catch (InvalidCredentialsException) {
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        } catch (AdminAccessDeniedException) {
            return back()->withErrors(['email' => 'Akun pengelola ini dinonaktifkan.'])->onlyInput('email');
        }

        if (! $admin->isSuperAdmin()) {
            return back()->withErrors(['email' => 'Dasbor ini hanya untuk super_admin.'])->onlyInput('email');
        }

        $admin->recordLogin($request->ip());

        Auth::guard('admin_web')->login($admin, remember: false);
        $request->session()->regenerate();

        return redirect()->intended(route('super_admin.dashboard'));
    }

    /** [#impure] */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin_web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super_admin.login');
    }
}
