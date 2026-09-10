<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Seluruh pembatas laju didefinisikan di SINI, satu berkas.
 *
 * Angkanya di config/sekarya.php. Yang menentukan keamanannya bukan hanya
 * angkanya, tapi KUNCI pembatasnya — lihat catatan per pembatas.
 */
final class RateLimitServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $limits = (array) config('sekarya.rate_limits');

        // Panggilan API biasa: per pengguna kalau sudah login, per IP kalau belum.
        // Per-IP saja akan menghukum seluruh kantor yang berbagi satu IP.
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute($limits['api'])
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Login dibatasi per EMAIL+IP, bukan per email saja: kalau per email,
        // penyerang bisa mengunci akun korban hanya dengan membanjiri
        // percobaan gagal. Kombinasi ini membatasi penyerang tanpa
        // memblokir pemilik akun yang sah dari jaringan lain.
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute($limits['login'])
            ->by($this->identity($request).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perMinute($limits['register'])
            ->by((string) $request->ip()));

        // Memasukkan kode verifikasi: inilah permukaan tebak-kode. Selain
        // batas ini, kode itu sendiri punya batas percobaan di database —
        // dua lapis, karena rate limit berbasis IP bisa dihindari dengan
        // rotasi IP sedangkan batas di database tidak.
        RateLimiter::for('verify', fn (Request $request): Limit => Limit::perMinute($limits['verify'])
            ->by($this->identity($request).'|'.$request->ip()));

        // Kirim ulang kode: per email, agar tidak ada yang bisa membanjiri
        // inbox orang lain dari banyak IP.
        RateLimiter::for('resend', fn (Request $request): Limit => Limit::perMinute($limits['resend'])
            ->by($this->identity($request)));

        RateLimiter::for('refresh', fn (Request $request): Limit => Limit::perMinute($limits['refresh'])
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Penulisan yang bisa membanjiri feed (buat task, ajukan penawaran).
        RateLimiter::for('write', fn (Request $request): Limit => Limit::perMinute($limits['write'])
            ->by($request->user()?->getAuthIdentifier() ?? $request->ip()));

        // Endpoint pengelola. Kuncinya diberi awalan `admin|` supaya kuota
        // pengelola tidak pernah berbagi ember dengan kuota pengguna: id
        // keduanya adalah bigint dari dua tabel berbeda, jadi admin id 7 dan
        // user id 7 akan saling menghabiskan kuota tanpa ada yang tahu.
        RateLimiter::for('admin', fn (Request $request): Limit => Limit::perMinute($limits['admin'])
            ->by('admin|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        // Login pengelola: permukaan tebak-sandi yang paling berharga di
        // aplikasi ini. Sama seperti login pengguna, kuncinya email + IP —
        // per email saja berarti siapa pun bisa mengunci pengelola dari
        // aplikasinya sendiri hanya dengan membanjiri percobaan gagal.
        RateLimiter::for('admin_login', fn (Request $request): Limit => Limit::perMinute($limits['admin_login'])
            ->by('admin|'.$this->identity($request).'|'.$request->ip()));
    }

    /** Identitas dari payload, dinormalkan sama seperti di DTO. */
    private function identity(Request $request): string
    {
        $value = (string) ($request->input('email') ?? $request->input('phone') ?? '');

        return mb_strtolower(trim($value)) ?: 'anonim';
    }
}
