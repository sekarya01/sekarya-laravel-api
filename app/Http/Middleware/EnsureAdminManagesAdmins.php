<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang kelompok rute `/admin/admins`: hanya super_admin.
 *
 * Middleware, bukan Policy lewat `->can()`, dan itu bukan selera. Aturan ini
 * KASAR — ia soal peran pemanggil, bukan soal objek tertentu — dan proyek ini
 * memang memisahkan keduanya: `->can()` untuk aturan per-objek, middleware
 * untuk aturan yang berlaku atas seluruh kelompok rute, dideklarasikan di
 * `routes/api.php` supaya seluruh aturannya terbaca dalam satu berkas.
 *
 * Yang menentukan pilihan itu satu hal lagi: BENTUK RESPONSNYA. Penolakan dari
 * Policy keluar sebagai `AccessDeniedHttpException` bawaan Laravel —
 * `{"message": "This action is unauthorized."}`, tanpa kode mesin. Padahal
 * CreateAdminAction menolak hal yang sama dengan `admin_access_denied` +
 * `context.reason`. Satu kegagalan logis dengan dua bentuk respons, bergantung
 * lapis mana yang kebetulan menangkapnya lebih dulu, adalah persis yang
 * membuat klien terpaksa bercabang pada `message`.
 *
 * Aturannya sendiri tidak ditulis di sini — dibaca dari AdminRole.
 */
final class EnsureAdminManagesAdmins
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (! $admin instanceof Admin) {
            throw AdminAccessDeniedException::becauseNotAnAdmin();
        }

        if (! $admin->role->canManageAdmins()) {
            throw AdminAccessDeniedException::becauseRoleCannotManageAdmins();
        }

        return $next($request);
    }
}
