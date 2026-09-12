<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Auth\ResetPasswordAction;
use App\Exceptions\Domain\InvalidPasswordResetTokenException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Form reset kata sandi lewat tautan sekali pakai dari email.
 *
 * Tautan kedaluwarsa oleh DUA kondisi: lewat 60 menit (standar broker
 * `auth.passwords.users.expire`) atau sudah terpakai (token dihapus saat
 * reset berhasil). Begitu tombol ditekan dan reset berhasil, tautan yang
 * sama langsung menampilkan halaman kedaluwarsa.
 */
final class PasswordResetController
{
    public function __construct(private readonly ResetPasswordAction $action) {}

    public function show(string $token, Request $request): View
    {
        $email = (string) $request->query('email', '');

        if ($email === '' || ! $this->action->tokenIsValid($email, $token)) {
            return view('password-reset.expired');
        }

        return view('password-reset.form', ['token' => $token, 'email' => $email]);
    }

    public function store(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:180'],
            'token' => ['required', 'string'],
            // Aturan yang sama seperti pendaftaran: panjang, campuran
            // huruf/angka, dicek ke basis data kebocoran kata sandi publik.
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()->uncompromised()],
        ], [
            'password.uncompromised' => 'Kata sandi ini pernah muncul di kebocoran data. Pilih yang lain.',
        ]);

        try {
            $this->action->handle($validated['email'], $validated['token'], $validated['password']);
        } catch (InvalidPasswordResetTokenException) {
            return view('password-reset.expired');
        }

        return redirect()->route('password.reset.success');
    }

    public function success(): View
    {
        return view('password-reset.success');
    }
}
