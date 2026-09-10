<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Akun pengelola baru.
 *
 * TIDAK ADA field `role`. Perannya dipaksa `admin` di Action — kalau peran
 * bisa dikirim klien, endpoint ini adalah jalan membuat super_admin kedua.
 *
 * `unique:admins,email` ikut menghitung baris yang sudah dihapus (soft
 * delete), jadi alamat pengelola yang pernah dihapus tetap terpakai. Itu
 * disengaja: barisnya masih dirujuk `admin_audit_logs`, dan memakai ulang
 * alamatnya untuk orang lain akan membuat jejak lama terbaca sebagai
 * perbuatan orang yang baru.
 */
final class CreateAdminRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:180', 'unique:admins,email'],
            'password' => [
                'required',
                'confirmed',
                // Lebih ketat daripada sandi pengguna (8, huruf+angka):
                // yang dijaga di sini kewenangan menyetujui uang. Simbol
                // diwajibkan, dan sandinya dicek terhadap basis data
                // kebocoran publik.
                Password::min((int) config('sekarya.admin.min_password_length'))
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Alamat ini sudah terpakai — termasuk oleh akun pengelola yang pernah dihapus.',
            'password.uncompromised' => 'Kata sandi ini pernah muncul di kebocoran data. Pilih yang lain.',
        ];
    }
}
