<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\RegisterData;
use App\Enums\UserActiveMode;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Daftar akun baru. TIDAK menerbitkan token.
 *
 * Akun dibuat berstatus PendingVerification dan tidak bisa apa-apa sampai
 * kode dari email dimasukkan. Karena itu Action ini mengembalikan User,
 * bukan pasangan token.
 */
final class RegisterUserAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly IssueVerificationCodeAction $issueCode,
    ) {}

    public function handle(RegisterData $data, ?string $ip = null): User
    {
        return $this->db->transaction(function () use ($data, $ip): User {
            $user = new User([
                // `name` DISINKRON dari depan+belakang, bukan input bebas:
                // seluruh pembaca lama (Resource, notifikasi, klien mobile)
                // tetap melihat satu nama tampilan yang sama.
                'name' => $data->displayName(),
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'username' => $data->username,
                'email' => $data->email,
                'phone' => $data->phone,
                'password' => Hash::make($data->password),
                'active_mode' => UserActiveMode::Hiring,
                'city' => $data->city,
                'province' => $data->province,
            ]);

            // `status` DISENGAJA tidak masuk daftar mass-assign: kolom ini
            // memberi hak akses, jadi tidak boleh bisa disetel dari array
            // atribut mana pun. Menyetelnya di sini, eksplisit, di luar
            // mass assignment.
            //
            // Menaruhnya di array di atas akan dibuang tanpa suara dan akun
            // tercipta dengan default kolom — itu bug yang sudah pernah
            // terjadi di sini.
            $user->status = UserStatus::PendingVerification;
            $user->save();

            // Cooldown tidak berlaku untuk kode pertama.
            $this->issueCode->handle($user, $ip, enforceCooldown: false);

            return $user;
        });
    }
}
