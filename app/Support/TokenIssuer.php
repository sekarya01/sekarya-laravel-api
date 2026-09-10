<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Admin;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/**
 * Satu-satunya tempat token diterbitkan, dirotasi, dan dicabut.
 *
 * Ada supaya aturan umur token dan aturan rotasi tidak tersebar di beberapa
 * Action. Kalau nanti umurnya berubah atau long_lived ikut dirotasi, hanya
 * kelas ini yang disunting.
 *
 * Melayani DUA jenis pemilik token: pengguna dan pengelola. Yang membedakan
 * keduanya hanya ability yang dilekatkan, dan itu ditanyakan kepada
 * pemiliknya (`accessAbility()`), bukan dicabang di sini — kalau dicabang,
 * aturan "logout mencabut long_lived juga" dan "access lama mati saat rotasi"
 * harus ditulis dua kali, dan yang satu akan tertinggal.
 *
 * Tipenya union `User|Admin`, bukan sebuah interface: `createToken()` dan
 * `tokens()` datang dari trait HasApiTokens milik Sanctum, dan trait bukan
 * kontrak — sebuah interface di sini harus menyalin tanda tangan Sanctum dan
 * akan pecah setiap kali Sanctum mengubahnya (dan Sanctum sudah pernah
 * mengubahnya: `$expiresAt` baru ada di v4).
 */
final class TokenIssuer
{
    /**
     * Pasangan token untuk sesi baru (setelah verifikasi atau login).
     *
     * Token lama dicabut lebih dulu: satu perangkat satu sesi. Kalau nanti
     * multi-perangkat diizinkan, bagian inilah yang dilonggarkan.
     *
     * @return array{access: NewAccessToken, long_lived: NewAccessToken}
     */
    public function issuePair(User|Admin $owner): array
    {
        $this->revokeAll($owner);

        return [
            'long_lived' => $this->createLongLived($owner),
            'access' => $this->createAccess($owner),
        ];
    }

    /**
     * Tukar long_lived token jadi access token baru.
     *
     * Access token LAMA dicabut di sini — itu inti aturannya: setelah token
     * baru diminta, yang lama tidak bisa dipakai lagi. Long_lived token
     * sendiri tidak diganti.
     */
    public function rotateAccess(User|Admin $owner): NewAccessToken
    {
        $this->revokeAccessTokens($owner);

        return $this->createAccess($owner);
    }

    /** Cabut semua access token, sisakan long_lived. */
    public function revokeAccessTokens(User|Admin $owner): void
    {
        $owner->tokens()
            ->where('name', config('sekarya.tokens.access_name'))
            ->delete();
    }

    /** Cabut seluruh token — logout menyeluruh. */
    public function revokeAll(User|Admin $owner): void
    {
        $owner->tokens()->delete();
    }

    public function accessTtlHours(): int
    {
        return (int) config('sekarya.tokens.access_ttl_hours');
    }

    private function createAccess(User|Admin $owner): NewAccessToken
    {
        return $owner->createToken(
            (string) config('sekarya.tokens.access_name'),
            [$owner->accessAbility()->value],
            now()->addHours($this->accessTtlHours()),
        );
    }

    private function createLongLived(User|Admin $owner): NewAccessToken
    {
        return $owner->createToken(
            (string) config('sekarya.tokens.long_lived_name'),
            // HANYA kemampuan refresh. Token ini tidak bisa memanggil
            // endpoint aplikasi apa pun, sesuai perannya.
            [$owner->refreshAbility()->value],
            now()->addDays((int) config('sekarya.tokens.long_lived_ttl_days')),
        );
    }
}
