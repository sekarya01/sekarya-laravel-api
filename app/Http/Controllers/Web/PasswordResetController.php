<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Auth\ResetPasswordAction;
use App\Exceptions\Domain\InvalidPasswordResetTokenException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            // Aturan disamakan dengan aplikasi mobile (SekaryaValidators):
            // min 8, ada angka, dan (huruf kapital ATAU karakter unik).
            // Satu pesan untuk ketiganya, sama seperti di mobile.
            'password' => [
                'required',
                'confirmed',
                'min:8',
                'regex:/[0-9]/',
                'regex:/([A-Z]|[^A-Za-z0-9])/',
            ],
        ], [
            'password.min' => 'Password min. 8 karakter, ada angka, dan (huruf kapital atau karakter unik).',
            'password.regex' => 'Password min. 8 karakter, ada angka, dan (huruf kapital atau karakter unik).',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
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
