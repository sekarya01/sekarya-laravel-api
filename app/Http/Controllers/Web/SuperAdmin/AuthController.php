<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\SuperAdmin;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Masuk/keluar dasbor super_admin (sesi, bukan token API).
 *
 * Aturannya sama seperti AdminLoginAction: hash dummy saat alamat tidak ada
 * ( anti enumeration timing ), status diperiksa SETELAH sandi benar, dan
 * hanya peran super_admin yang boleh masuk — admin biasa tetap lewat API.
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

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($validated['email']));
        $admin = Admin::query()->where('email', $email)->first();

        $hash = $admin?->password ?? '$2y$12$'.str_repeat('x', 53);

        if (! Hash::check($validated['password'], $hash) || ! $admin instanceof Admin) {
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }

        if (! $admin->status->isActive()) {
            return back()->withErrors(['email' => 'Akun pengelola ini dinonaktifkan.'])->onlyInput('email');
        }

        if (! $admin->isSuperAdmin()) {
            return back()->withErrors(['email' => 'Dasbor ini hanya untuk super_admin.'])->onlyInput('email');
        }

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        Auth::guard('admin_web')->login($admin, remember: false);
        $request->session()->regenerate();

        return redirect()->intended(route('super_admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin_web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('super_admin.login');
    }
}
