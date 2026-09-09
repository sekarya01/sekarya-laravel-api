<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\TokenAbility;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;

/**
 * Satu-satunya tempat token diterbitkan, dirotasi, dan dicabut.
 *
 * Ada supaya aturan umur token dan aturan rotasi tidak tersebar di beberapa
 * Action. Kalau nanti umurnya berubah atau long_lived ikut dirotasi, hanya
 * kelas ini yang disunting.
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
    public function issuePair(User $user): array
    {
        $this->revokeAll($user);

        return [
            'long_lived' => $this->createLongLived($user),
            'access' => $this->createAccess($user),
        ];
    }

    /**
     * Tukar long_lived token jadi access token baru.
     *
     * Access token LAMA dicabut di sini — itu inti aturannya: setelah token
     * baru diminta, yang lama tidak bisa dipakai lagi. Long_lived token
     * sendiri tidak diganti.
     */
    public function rotateAccess(User $user): NewAccessToken
    {
        $this->revokeAccessTokens($user);

        return $this->createAccess($user);
    }

    /** Cabut semua access token, sisakan long_lived. */
    public function revokeAccessTokens(User $user): void
    {
        $user->tokens()
            ->where('name', config('sekarya.tokens.access_name'))
            ->delete();
    }

    /** Cabut seluruh token — logout menyeluruh. */
    public function revokeAll(User $user): void
    {
        $user->tokens()->delete();
    }

    public function accessTtlHours(): int
    {
        return (int) config('sekarya.tokens.access_ttl_hours');
    }

    private function createAccess(User $user): NewAccessToken
    {
        return $user->createToken(
            (string) config('sekarya.tokens.access_name'),
            [TokenAbility::Access->value],
            now()->addHours($this->accessTtlHours()),
        );
    }

    private function createLongLived(User $user): NewAccessToken
    {
        return $user->createToken(
            (string) config('sekarya.tokens.long_lived_name'),
            // HANYA kemampuan refresh. Token ini tidak bisa memanggil
            // endpoint aplikasi apa pun, sesuai perannya.
            [TokenAbility::Refresh->value],
            now()->addDays((int) config('sekarya.tokens.long_lived_ttl_days')),
        );
    }
}
