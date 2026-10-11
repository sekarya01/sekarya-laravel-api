<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Data\Admin\AdminLoginData;
use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

/**
 * SATU-SATUNYA pemeriksa kredensial pengelola — dipakai login API
 * (AdminLoginAction, token) dan login dasbor web (sesi). Dulu web menyalin
 * aturannya; salinan seperti itu yang diam-diam tertinggal saat aturannya
 * diperketat.
 *
 * Tidak menulis apa pun: pemanggil yang memutuskan apakah login benar-benar
 * terjadi (dasbor masih menolak peran selain super_admin) lalu mencatatnya
 * lewat Admin::recordLogin().
 */
final class VerifyAdminCredentialsAction
{
    public function handle(AdminLoginData $data): Admin
    {
        $admin = Admin::query()->where('email', $data->email)->first();

        // Hash dummy tetap diperiksa saat akunnya tidak ada, supaya waktu
        // respons untuk "alamat tidak terdaftar" dan "sandi salah" tidak
        // berbeda dan tidak bisa dipakai menemukan alamat pengelola.
        $hash = $admin?->password ?? '$2y$12$'.str_repeat('x', 53);

        if (! Hash::check($data->password, $hash) || ! $admin instanceof Admin) {
            throw InvalidCredentialsException::make();
        }

        // Diperiksa SETELAH sandinya benar. Diperiksa sebelum itu, jawaban
        // "akun dinonaktifkan" akan membocorkan alamat mana yang ada
        // kepada orang yang belum tahu sandinya.
        if (! $admin->status->isActive()) {
            throw AdminAccessDeniedException::becauseSuspended();
        }

        return $admin;
    }
}
